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
 * Edit form base for a task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

use block_deft\socket;
use cache;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/deft/lib.php');

use block_deft\manager;
use context;
use context_user;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use block_deft\task;

/**
 * Edit form base for a task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class edit_task extends dynamic_form {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->get_context_for_dynamic_submission()->id);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
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
         require_capability('block/deft:edit', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $OUTPUT;

        if ($data = $this->get_data()) {
            $instance = $this->get_context_for_dynamic_submission()->instanceid;
            $contextid = $this->get_context_for_dynamic_submission()->id;
            if (empty($data->id)) {
                unset($data->id);
                unset($data->contextid);
                $record = (object) [
                    'type' => $this->type,
                    'configdata' => json_encode($data),
                    'sortorder' => task::count_records(['instance' => $instance]),
                    'instance' => $instance,
                    'visible' => 0,
                    'statedata' => '{}',
                ];
                $task = $this->get_task(0, $record);
                $task->create();
                $formclass = "\\block_deft\\form\\status_$record->type";
                $form = new $formclass(null, null, 'post', '', [], true, [
                    'contextid' => $contextid,
                    'id' => $task->get('id'),
                ]);
                $form->set_data_for_dynamic_submission([
                    'contextid' => $contextid,
                    'id' => $task->get('id'),
                ]);
                return [
                    'canedit' => true,
                    'contextid' => $contextid,
                    'form' => $form->render(),
                    'id' => $task->get('id'),
                    'configdata' => $task->get_config(),
                    'type' => $task->get('type'),
                ];
            } else {
                $task = $this->get_task($data->id);
                unset($data->id);
                unset($data->contextid);
                $task->set('configdata', json_encode($data));
                $task->update();
                $returndata = [
                    'html' => $OUTPUT->render_from_template('block_deft/taskinfo', [
                        'canedit' => true,
                        'form' => !empty($form) ? $form->render() : '',
                        'contextid' => $contextid,
                        'configdata' => $task->get_config(),
                        'id' => $task->get('id'),
                        'type' => $task->get('type'),
                    ]),
                    'contextid' => $data->contextid,
                    'id' => $task->get('id'),
                ];

                // Update block display.
                if (!empty($task->get_state()->visible)) {
                    $socket = $this->get_socket($this->get_context_for_dynamic_submission());
                    $socket->dispatch();
                }

                return $returndata;
            }
        }
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        $mform = $this->_form;

        if (
            !empty((int) $this->_ajaxformdata['id'])
            && $task = $this->get_task($this->_ajaxformdata['id'])
        ) {
            $configdata = (array) $task->get_config();
            unset($configdata['contextid']);
            $mform->setDefault('id', $task->get('id'));
            $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);
            foreach ($configdata as $field => $value) {
                if ($field !== 'jsondata') {
                    $mform->setDefault($field, $value);
                }
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

        $manager = $this->get_manager($this->get_context_for_dynamic_submission());
        $data = $manager->export_for_template($OUTPUT);

        // Update block display.
        $socket = $this->get_socket($this->get_context_for_dynamic_submission());
        $socket->dispatch();

        return [
            'html' => $OUTPUT->render_from_template('block_deft/tasks', $data),
        ];
    }

    /**
     * Get task
     *
     * @param int $id record id
     * @param stdClass $record
     * @return task
     *
     */
    protected function get_task($id, $record = null) {
        if (empty($id)) {
            return new task($id, $record);
        }

        $cache = cache::make('block_deft', 'tasks');
        $tasks = $cache->get($this->get_context_for_dynamic_submission()->instanceid);
        $task = new task();
        $task->from_record($tasks[$id]);

        return $task;
    }

    /**
     * Get manager
     *
     * @param context $context context
     * @return object
     */
    protected function get_manager($context) {
        return new manager($context);
    }

    /**
     * Get socket
     *
     * @param context $context context
     * @return object
     */
    protected function get_socket($context) {
        return new socket($context);
    }
}
