<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class containing data for deft choice block.
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/comment/lib.php');

use comment;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use core_course\external\course_summary_exporter;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class containing data for deft choice block.
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choice extends text implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     */
    public function __construct($context, $task) {
        $this->task = $task;
        $this->context = $context;
        $this->config = json_decode($task->configdata);
        $this->state = json_decode($task->statedata);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $OUTPUT, $USER;

        if (empty($this->state->visible)) {
            return '';
        }

        $config = get_config('block_deft');

        $form = new \block_deft\form\choice(null, null, 'post', '', ['onsubmit' => 'return false;'], true, [
            'id' => $this->task->id,
            'contextid' => $this->context->id,
        ]);
        $form->set_data_for_dynamic_submission();

        $summary = new summary($this->context, $this->task);

        return [
            'contextid' => $this->context->id,
            'name' => $this->state->showtitle ? $this->config->name : '',
            'question' => s($this->config->question),
            'select' => $form->render(),
            'summary' => $output->render($summary),
            'visible' => true,
        ];
    }
}
