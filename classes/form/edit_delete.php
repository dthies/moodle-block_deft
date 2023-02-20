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
 * Form base for delete task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use block_deft\task;

/**
 * Form base for delete task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class edit_delete extends edit_task {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->get_context_for_dynamic_submission()->id);
        $mform->setType('contextid', PARAM_INT);
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
            if (!empty($data->id)) {
                $task = $this->get_task($data->id);
                $state = $task->get_state();
                $task->delete();
                $tasks = task::get_records(['instance' => $this->get_context_for_dynamic_submission()->instanceid]);
                foreach (array_values($tasks) as $sortorder => $task) {
                    $task->set('sortorder', $sortorder);
                    $task->update();
                }

                // Update block display.
                if (!empty($state->visible)) {
                    $socket = $this->get_socket($this->get_context_for_dynamic_submission());
                    $socket->dispatch();
                }

                return [
                    'id' => $data->id,
                ];
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
        parent::set_data_for_dynamic_submission();

        $mform = $this->_form;

        $id = $mform->getElementValue('id');
        $task = $this->get_task($id);
        $mform->addElement('html', get_string('confirmdelete', 'block_deft', $task->get_config()->name));
    }
}
