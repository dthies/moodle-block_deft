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
 * Task manager
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft;

defined('MOODLE_INTERNAL') || die();

use block_deft\output\main;
use templatable;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;

require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Task manager
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager implements renderable, templatable {

    /** @var Usage report data */
    protected $report = null;

    /**
     * Constructor
     *
     * @param context $context Context of block
     */
    public function __construct ($context) {
        global $DB;

        $this->context = $context;

        if (is_siteadmin()) {
            $socket = new socket($context);
            $this->report = $socket->execute([
                'action' => 'report',
            ]) ?? new stdClass();
            $this->report->enableupdating = get_config('block_deft', 'enableupdating');
            $this->report->registered = !!$DB->get_field('lti_types', 'clientid', ['tooldomain' => 'deftly.us']);
            $this->report->url = (new moodle_url('/admin/settings.php', ['section' => 'blocksettingdeft']))->out();
        }
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return \stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $PAGE;

        $tasks = task::get_records(['instance' => $this->context->instanceid], 'sortorder');
        $tasks = array_values($tasks);
        $tasklist = [];
        foreach ($tasks as $task) {
            $record = $task->to_record();
            $record->configdata = $task->get_config();
            $record->statedata = $task->get_state();
            $formclass = "\\block_deft\\form\\status_$record->type";
            $form = new $formclass(null, null, 'post', '', [], true, [
                'contextid' => $this->context->id,
                'id' => $record->id,
            ]);
            $form->set_data_for_dynamic_submission([
                'contextid' => $this->context->id,
                'id' => $record->id,
            ] + (array) $record->statedata);
            $record->form = $form->render();
            $tasklist[] = $record;
        }

        $instance = block_instance_by_id($this->context->instanceid);

        $main = new main($this->context, $instance->config);
        $data = ['canuse' => false] + $main->export_for_template($output);

        try {
            $blockpresent = !empty($PAGE->blocks->find_instance($this->context->instanceid));
        } catch (\block_not_on_page_exception $e) {
            $blockpresent = false;
        }

        return [
            'blockpresent' => $blockpresent,
            'canedit' => has_capability('block/deft:edit', $this->context),
            'contextid' => $this->context->id,
            'id' => $this->context->instanceid,
            'main' => $output->render_from_template('block_deft/main', $data),
            'tasks' => array_values($tasklist),
            'report' => $this->report,
        ];
    }
}
