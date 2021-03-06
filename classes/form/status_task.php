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
 * Form base for modifying task status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

use context;
use context_user;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use block_deft\task;

/**
 * Form base for modifying task status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class status_task extends edit_task {

    /** @var config data */
    protected $configdata;

    /** @var state data */
    protected $statedata;

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $USER;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->get_context_for_dynamic_submission()->id);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('advcheckbox', 'visible', '', get_string('visible', 'block_deft'));
        $mform->setType('visible', PARAM_BOOL);
        $mform->setDefault('visible', get_config('block_deft', 'addcomments'));

        $mform->addElement('advcheckbox', 'showtitle', '', get_string('showtitle', 'block_deft'));
        $mform->setType('showtitle', PARAM_BOOL);
        $mform->setDefault('showtitle', get_config('block_deft', 'showtitle'));
        $mform->disabledIf('showtitle', 'visible', 0);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     */
    protected function check_access_for_dynamic_submission(): void {
         require_capability('block/deft:manage', $this->get_context_for_dynamic_submission());
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
            if (!empty($data->id)) {
                $task = new task($data->id);
                unset($data->id);
                $task->set('statedata', json_encode($data));
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
        $mform = $this->_form;

        if (
            !empty((int) $this->_ajaxformdata['id'])
            && $task = new task($this->_ajaxformdata['id'])
        ) {
            $this->configdata = (array) $task->get_config();
            $this->statedata = (array) $task->get_state();
            $mform->setDefault('id', $task->get('id'));
            $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);
            foreach ($this->statedata as $field => $value) {
                if ($field !== 'jsondata') {
                    $mform->setDefault($field, $value);
                }
            }

            $mform->insertElementBefore(
                $mform->createElement('static', 'name', get_string('taskname', 'block_deft'), s($this->configdata['name'])),
                'visible'
            );
        }
    }
}
