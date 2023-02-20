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
 * Edit form for venue task
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
 * Edit form for venue task
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_venue extends edit_task {

    /** @var {string} $type Type of task */
    protected $type = 'venue';

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        parent::definition();

        $mform->addElement('textarea', 'content', get_string('content', 'page'));
        $mform->setType('content', PARAM_CLEANHTML);

        $mform->addElement('editor', 'intro', get_string('description'), null, $this->options());

        $mform->addElement('text', 'limit', get_string('limit', 'block_deft'));
        $mform->setType('limit', PARAM_INT);

        $mform->addElement('select', 'windowoption', get_string('windowoption', 'block_deft'), [
            'openinpopup' => get_string('openinpopup', 'block_deft'),
            'openinwindow' => get_string('openinwindow', 'block_deft'),
        ]);

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
            $configdata = $task->get_config();
            unset($configdata->contextid);
            $draftid = $data->intro->itemid ?? 0;
            $format = $configdata->intro->format;
            $intro = file_prepare_draft_area(
                $draftid,
                $this->get_context_for_dynamic_submission()->id,
                'block_deft',
                'venue',
                $task->get('id'),
                [
                    'subdirs' => true,
                ],
                $configdata->intro->text
            );
            $configdata = (array) $configdata;
            $configdata['intro'] = [
                'text' => $intro,
                'itemid' => $draftid,
                'format' => $format,
            ];

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
     * Get file options
     *
     * @return array
     */
    protected function options(): array {
        global $CFG;

        return [
            'subdirs' => true,
            'maxbytes' => $CFG->maxbytes,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => $this->get_context_for_dynamic_submission(),
            'noclean' => false,
            'trusttext' => false,
            'enable_filemanagement' => true,
        ];
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
            $returndata = parent::process_dynamic_submission();
            if (empty($data->id)) {
                $data->id = $returndata['id'];
            }

            $data->intro['text'] = file_save_draft_area_files(
                $data->intro['itemid'],
                $this->get_context_for_dynamic_submission()->id,
                'block_deft',
                'venue',
                $data->id,
                [
                    'subdirs' => true,
                ],
                $data->intro['text']
            );
            $data->intro['itemid'] = $data->id;

            $task = $this->get_task($data->id);
            unset($data->id);
            unset($data->contextid);
            $task->set('configdata', json_encode($data));
            $task->update();

            return $returndata;
        }
    }
}
