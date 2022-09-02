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
 * Library functions for Deft.
 *
 * @package   block_deft
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

use block_deft\output\main;
use block_deft\socket;
use block_deft\task;

/**
 * Validate comment parameter before perform other comments actions
 *
 * @package  block_deft
 * @category comment
 *
 * @param stdClass $commentparam {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return boolean
 */
function block_deft_comment_validate($commentparam) {
    if ($commentparam->commentarea != 'task') {
        throw new comment_exception('invalidcommentarea');
    }
    if (
        !$task = new task($commentparam->itemid)
        || $task->get('type') != 'comments'
    ) {
        throw new comment_exception('invalidcommentitemid');
    }
    return true;
}

/**
 * Running addtional permission check on plugins
 *
 * @package  block_deft
 * @category comment
 *
 * @param stdClass $args
 * @return array
 */
function block_deft_comment_permissions($args) {
    return [
        'post' => true,
        'view' => true,
    ];
}

/**
 * Validate comment data before displaying comments
 *
 * @package  block_deft
 * @category comment
 *
 * @param stdClass $comments
 * @param stdClass $args
 * @return boolean
 */
function block_deft_comment_display($comments, $args) {
    if ($args->commentarea != 'task') {
        throw new comment_exception('invalidcommentarea');
    }
    if (
        !$task = new task($args->itemid)
        || $task->get('type') != 'comments'
    ) {
        throw new comment_exception('invalidcommentitemid');
    }
    return $comments;
}

/**
 * Serve the comments as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_choose($args) {
    global $CFG, $DB, $USER;

    $context = $args['context'];
    $id = $args['id'];
    $option = (string) $args['option'];

    if ($context->contextlevel != CONTEXT_BLOCK) {
        return null;
    }
    require_capability('block/deft:choose', $context);

    $task = new task($id);
    $config = $task->get_config();
    if (!empty($task->get_state()->preventresponse)) {
        return null;
    }

    $timenow = time();
    if ($option == '') {
        $DB->delete_records('block_deft_response', [
            'task' => $id,
            'userid' => $USER->id,
        ]);
    } else if ($record = $DB->get_record('block_deft_response', ['task' => $id, 'userid' => $USER->id])) {
        $record->response = $config->option[$option];
        $record->timemodified = $timenow;
        $DB->update_record('block_deft_response', $record);
    } else {
        $DB->insert_record('block_deft_response', [
            'task' => $id,
            'userid' => $USER->id,
            'response' => $config->option[$option],
            'timecreated' => $timenow,
            'timemodified' => $timenow,
        ]);
    }

    // Clear the results cache.
    $cache = cache::make('block_deft', 'results');
    $cache->delete($id);

    $socket = new socket($context);
    $socket->dispatch();

    return 'change';
}

/**
 * Serve the comments as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_content($args) {
    global $CFG, $OUTPUT;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_BLOCK) {
        return null;
    }

    $instance = block_instance_by_id($context->instanceid);

    $data = $instance->export_for_template($OUTPUT);

    $jsondata = json_decode($args['jsondata']);
    if (!empty($jsondata->opencomments)) {
        foreach ($data['tasks'] as $task) {
            if (in_array($task->id, $jsondata->opencomments)) {
                $task->opencomments = 1;
                $comments = new \block_deft\output\comments($context, $task);
                $task->html = $OUTPUT->render($comments);
            }
        }
    }

    return $OUTPUT->render_from_template('block_deft/view', $data);
}

/**
 * Serve the comments as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_test($args) {
    global $CFG, $OUTPUT;

    $context = $args['context'];

    if (!is_siteadmin()) {
        return null;
    }

    $socket = new socket($context);
    $socket->dispatch();

    return get_string('messagesent', 'block_deft');

}

/**
 * Callback to remove linked logins for deleted users.
 *
 * @param stdClass $user
 */
function block_deft_pre_user_delete($user) {
    global $DB;

    if (!$tasks = $DB->get_fieldset_select('block_deft_response', 'task', 'userid = ?', [$user->id])) {
        return;
    }

    // Clear the results cache.
    $cache = cache::make('block_deft', 'results');
    $cache->delete_many($tasks);

    $DB->delete_records('block_deft_response', ['userid' => $user->id]);
}
