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
 * Delete comment dynamic form
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/deft/lib.php');

use block_deft\comment;
use context;
use core_user;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use stdClass;
use block_deft\task;

/**
 * Delete comment dynamic form
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class delete_comment extends add_comment {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'commentid');
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
            $options = new stdClass();
            $options->area = 'task';
            $options->context = $this->get_context_for_dynamic_submission();
            $options->itemid = $data->id;
            $options->component = 'block_deft';
            $comment = new comment($options);
            $comment->delete($data->commentid);
        }
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $mform = $this->_form;

        if (
            !empty((int) $this->_ajaxformdata['id'])
            && $task = $this->get_task($this->_ajaxformdata['id'])
        ) {
            $comment = $DB->get_record('comments', array('id' => $this->_ajaxformdata['commentid']), '*', MUST_EXIST);
            $configdata = (array) $task->get_config();
            $mform->setDefault('id', $task->get('id'));
            $mform->setDefault('contextid', $this->get_context_for_dynamic_submission()->id);
            $mform->setDefault('commentid', $this->_ajaxformdata['commentid']);
            $user = core_user::get_user($comment->userid);
            $mform->addElement('html', get_string('deletecommentbyon', 'core', [
                'user' => fullname($user),
                'time' => userdate($comment->timecreated, get_string('strftimedaydatetime', 'langconfig')),
            ]));
        }
    }
}
