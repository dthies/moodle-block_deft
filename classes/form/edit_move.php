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
 * Form base for moving a task
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
 * Form base for moving a task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class edit_move extends edit_task {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->get_context_for_dynamic_submission()->id);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'position', '', '');

    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        if (!($data = $this->get_data()) || empty($data->id)) {
            return '';
        }

        $instance = $this->get_context_for_dynamic_submission()->instanceid;
        $tasks = $this->get_records(
            ['instance' => $instance],
            'sortorder'
        );
        $task = $this->get_task($data->id);
        $current = $task->get('sortorder');
        if ($current == $data->position) {
            return '';
        }
        $task = array_splice($tasks, $current, 1);
        array_splice($tasks, (int) $data->position, 0, $task);

        foreach ($tasks as $sortorder => $task) {
            $task->set('sortorder', $sortorder);
            $task->update();
        }

        $tasks = $this->get_records(
            ['instance' => $instance],
            'sortorder'
        );

        // Update block display.
        $socket = $this->get_socket($this->get_context_for_dynamic_submission());
        $socket->dispatch();

        return [
            'order' => array_map(function($task) {
                return $task->get('id');
            }, $tasks),
        ];
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

        $instance = $this->get_context_for_dynamic_submission()->instanceid;
        $tasks = $this->get_records(['instance' => $instance], 'sortorder');
        foreach ($tasks as $task) {
            $options[] = $task->get_config()->name;
        }
        $mform->removeElement('position');
        $mform->addElement('select', 'position', get_string('position', 'block_deft'), $options);
    }

    /**
     * Get task records
     *
     * @param array $params
     * @param string $sort
     * @return array task list
     */
    protected function get_records(array $params, string $sort = ''): array {
        return task::get_records($params, $sort);
    }
}
