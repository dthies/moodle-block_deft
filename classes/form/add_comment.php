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
 * Add comment dynamic form
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/deft/lib.php');

use cache;
use block_deft\comment;
use context;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use stdClass;
use block_deft\task;

/**
 * Add comment dynamic form
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class add_comment extends dynamic_form {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $this->get_context_for_dynamic_submission()->id);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('textarea', 'content', get_string('content', 'block_deft'));
        $mform->setType('content', PARAM_TEXT);
        $mform->addRule('content', null, 'required', null, 'client');
        $mform->addRule('content', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
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
         require_capability('moodle/comment:post', $this->get_context_for_dynamic_submission());
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
            $options = new stdClass();
            $options->area = 'task';
            $options->context = $this->get_context_for_dynamic_submission();
            $options->itemid = $data->id;
            $options->component = 'block_deft';
            $comment = new comment($options);
            $comment->add($data->content);
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
            $mform->setDefault('id', $task->get('id'));
            $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);
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
}
