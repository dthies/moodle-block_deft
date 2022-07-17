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
class text implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     */
    public function __construct($context, $task) {
        $this->context = $context;
        $this->task = $task;
        $this->config = json_decode($task->configdata);
        $this->state = json_decode($task->statedata);
        if (empty($this->state->showcomments) || empty($this->config->addcomments)) {
            return;
        }
        $course = get_course($context->get_course_context()->instanceid);
        $args = new stdClass();
        $args->context   = $context;
        $args->course    = $course;
        $args->area      = 'task';
        $args->itemid    = $task->id;
        $args->component = 'block_deft';
        $args->linktext  = get_string('showcomments');
        $args->notoggle  = false;
        $args->showcount  = true;
        $args->autostart = true;
        $args->displaycancel = false;
        $this->comment = new comment($args);
        $this->comment->set_view_permission(true);
        $this->comment->set_fullwidth();
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

        return [
            'name' => $this->state->showtitle ? $this->config->name : '',
            'content' => $this->config->content,
            'comments' => !empty($this->comment) ? $this->comment->output(true) : null,
        ];
    }
}
