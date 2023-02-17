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
 * Edit form for choice task
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
 * Edit form for choice task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class edit_choice extends edit_task {

    /**
     * @var string $type type of task
     */
    protected $type = 'choice';

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        parent::definition();

        $mform->addElement('textarea', 'question', get_string('configquestion', 'block_deft'));
        $mform->setType('question', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'charttype', get_string('charttype', 'block_deft'));
        $mform->setType('charttype', PARAM_TEXT);
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

            $repeatno = max(3, count(array_filter($configdata['option'] ?? [])) + 1);
        } else {
            $repeatno = 3;
        }
        $repeatarray[] = $mform->createElement('text', 'option', get_string('optionno', 'choice'));
        $mform->setType('option', PARAM_CLEANHTML);

        $repeateloptions = [];
        $this->repeat_elements($repeatarray, $repeatno,
        $repeateloptions, 'option_repeats', 'option_add_fields', 3, null, true);
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
            $this->_ajaxformdata['option_repeats'] = count(array_filter($data->option));
        }
        return parent::process_dynamic_submission();
    }
}
