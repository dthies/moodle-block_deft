<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Select form base for choice task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

use block_deft\socket;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/deft/lib.php');

use block_deft\manager;
use block_deft\task;
use context;
use context_user;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;

/**
 * Select form base for choice task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class choice extends dynamic_form {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $USER;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
    }

    /**
     * Return form context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $contextid = $this->_ajaxformdata['contextid'];

        return context::instance_by_id($contextid);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     */
    protected function check_access_for_dynamic_submission(): void {
         require_capability('block/deft:choose', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        if ($data = $this->get_data()) {
            unset($data->contextid);
            $instance = $this->get_context_for_dynamic_submission()->instanceid;
            if (empty($data->id)) {
                unset($data->id);
                $task = new task(0, [
                    'type' => $this->type,
                    'configdata' => json_encode($data),
                    'sortorder' => task::count_records(['instance' => $instance]),
                    'instance' => $instance,
                ]);
                $task->create();
            } else {
                $task = new task($data->id);
                unset($data->id);
                $task->set('configdata', json_encode($data));
                $task->update();
            }

        }
        return $this->task_html();
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB, $USER;

        $mform = $this->_form;

        if (
            !empty((int) $this->_ajaxformdata['id'])
            && $task = new task($this->_ajaxformdata['id'])
        ) {
            $configdata = (array) $task->get_config();
            $mform->setDefault('id', $task->get('id'));
            $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);
            foreach ($configdata as $field => $value) {
                if ($field !== 'jsondata') {
                    $mform->setDefault($field, $value);
                }
            }
            if ($response = $DB->get_record('block_deft_response', [
                'task' => $task->get('id'),
                'userid' => $USER->id,
            ])) {
                $mform->setDefault(
                    'option',
                    array_search($response->response, $configdata['option'])
                );
            }
            if (
                !has_capability('block/deft:choose', $this->get_context_for_dynamic_submission()) ||
                (($statedata = $task->get_state()) && $statedata->preventresponse)
            ) {
                $mform->disabledIf('option', 'id', 'neq', '-1');
            }
        }
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $url = new moodle_url('/blocks/deft/manage.php', [
            'id' => $this->get_context_for_dynamic_submission()->instanceid,
        ]);

        return $url;
    }

    /**
     * HTML to display
     */
    protected function task_html(): array {
        global $OUTPUT;

        $manager = new manager($this->get_context_for_dynamic_submission());
        $data = $manager->export_for_template($OUTPUT);

        // Update block display.
        $socket = new socket($this->get_context_for_dynamic_submission());
        $socket->dispatch();

        return [
            'html' => $OUTPUT->render_from_template('block_deft/tasks', $data),
        ];
    }

    /**
     * Definition after data
     */
    public function definition_after_data() {
        $mform = $this->_form;

        $id = $mform->getElementValue('id');
        $task = new task($id);

        $mform->addElement(
            'select',
            'option',
            '',
            ['' => get_string('select')] + array_filter($task->get_config()->option),
            ['onsubmit' => 'return false;']
        );

    }
}
