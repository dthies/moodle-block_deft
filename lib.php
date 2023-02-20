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
require_once($CFG->dirroot . '/lib/accesslib.php');

use block_deft\output\view;
use block_deft\venue_manager;
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
    $cache = cache::make('block_deft', 'tasks');
    if (
        (!$tasks = $cache->get($commentparam->context->instanceid))
        || (!$task = $tasks[$commentparam->itemid])
        || $task->type != 'comments'
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
    $cache = cache::make('block_deft', 'tasks');
    if (
        (!$tasks = $cache->get($args->context->instanceid))
        || (!$task = $tasks[$args->itemid])
        || $task->type != 'comments'
    ) {
        throw new comment_exception('invalidcommentitemid');
    }
    return $comments;
}

/**
 * Provide venue user information
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_venue($args) {
    global $DB, $OUTPUT, $USER, $PAGE;

    $context = $args['context'];
    $peerid = $args['peerid'];
    $peer = $DB->get_record('block_deft_peer', [
        'id' => $peerid
    ]);

    if (!$user = core_user::get_user($peer->userid)) {
        return '';
    }
    $url = new moodle_url('/user/view.php', [
        'id' => $user->id,
        'course' => $context->get_course_context->instance,
    ]);
    $user->fullname = fullname($user);
    $userpicture = new user_picture($user);
    $user->pictureurl = $userpicture->get_url($PAGE, $OUTPUT);
    $user->avatar = $OUTPUT->user_picture($user, [
        'class' => 'card-img-top',
        'link' => false,
        'size' => 256,
    ]);
    $user->manage = has_capability('block/deft:moderate', $context);
    $user->profileurl = $url->out(false);

    return $OUTPUT->render_from_template('block_deft/venue_user', [
        'peerid' => $peerid,
        'user' => $user,
    ]);
}

/**
 * Serve the comments as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_choose($args) {
    global $DB, $USER;

    $context = $args['context'];
    $id = $args['id'];
    $option = (string) $args['option'];

    if ($context->contextlevel != CONTEXT_BLOCK) {
        return null;
    }
    require_capability('block/deft:choose', $context);

    $cache = cache::make('block_deft', 'tasks');
    $tasks = $cache->get($context->instanceid);
    $task = new task();
    $task->from_record($tasks[$id]);
    $config = $task->get_config();
    if (!empty($task->get_state()->preventresponse)) {
        return null;
    }

    $timenow = time();
    $cache = cache::make('block_deft', 'results');
    if ($cache->get($id . 'x' . $USER->id) === $config->option[$option]) {
        return '';
    } else if ($option == '') {
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
    $cache->delete($id);
    $cache->delete($id . 'x' . $USER->id);

    $cache->get($id);
    $cache->get($id . 'x' . $USER->id);

    if (!empty($task->get_state()->showsummary)) {
        $socket = new socket($context);
        $socket->dispatch();
    }

    $params = [
        'context' => $context,
        'objectid' => $task->get('id'),
    ];

    $event = \block_deft\event\choice_submitted::create($params);
    $event->trigger();

    return 'change';
}

/**
 * Serve the comments as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_content($args) {
    global $OUTPUT;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_BLOCK) {
        return null;
    }

    $jsondata = json_decode($args['jsondata']);

    $view = new view($context, $jsondata);

    $data = $view->export_for_template($OUTPUT);

    if (!empty($jsondata->lastmodified) && ($jsondata->lastmodified >= $data['lastmodified'])) {
        return '';
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

    $DB->delete_records_select(
        'block_deft_signal',
        'frompeer IN (SELECT id FROM {block_deft_peer} WHERE userid = :userid',
        ['userid' => $user->id]
    );
    $DB->delete_records_select(
        'block_deft_signal',
        'topeer IN (SELECT id FROM {block_deft_peer} WHERE userid = :userid',
        ['userid' => $user->id]
    );
    $DB->delete_records('block_deft_peer', ['userid' => $user->id]);
}

/**
 * Plugin files for block deft
 *
 * @param stdClass $course course object
 * @param stdClass $birecordorcm block instance record
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 * @todo MDL-36050 improve capability check on stick blocks, so we can check user capability before sending images.
 */
function block_deft_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_BLOCK) {
        send_file_not_found();
    }

    // If block is in course context, then check if user has capability to access course.
    if ($context->get_course_context(false)) {
        require_course_login($course);
    } else if ($CFG->forcelogin) {
        require_login();
    } else {
        // Get parent context and see if user have proper permission.
        $parentcontext = $context->get_parent_context();
        if ($parentcontext->contextlevel === CONTEXT_COURSECAT) {
            // Check if category is visible and user can view this category.
            if (!core_course_category::get($parentcontext->instanceid, IGNORE_MISSING)) {
                send_file_not_found();
            }
        } else if ($parentcontext->contextlevel === CONTEXT_USER && $parentcontext->instanceid != $USER->id) {
            // The block is in the context of a user, it is only visible to the user who it belongs to.
            send_file_not_found();
        }
        // At this point there is no way to check SYSTEM context, so ignoring it.
    }

    if ($filearea !== 'venue') {
        send_file_not_found();
    }

    $fs = get_file_storage();

    $filename = array_pop($args);
    $taskid = array_shift($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';

    if ((!$file = $fs->get_file($context->id, 'block_deft', 'venue', $taskid, $filepath, $filename)) || $file->is_directory()) {
        send_file_not_found();
    }

    if ($parentcontext = context::instance_by_id($birecordorcm->parentcontextid, IGNORE_MISSING)) {
        if ($parentcontext->contextlevel == CONTEXT_USER) {
            // Force download on all personal pages including /my/
            // because we do not have reliable way to find out from where this is used.
            $forcedownload = true;
        }
    } else {
        // Weird, there should be parent context, better force dowload then.
        $forcedownload = true;
    }

    // NOTE: it would be nice to have file revisions here, for now rely on standard file lifetime,
    // do not lower it because the files are dispalyed very often.
    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Provide venue manager for modal
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function block_deft_output_fragment_venue_manager($args) {
    global $PAGE, $SESSION;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_BLOCK) {
        return null;
    }

    require_capability('block/deft:joinvenue', $context);

    $jsondata = json_decode($args['jsondata']);
    $taskid = $args['taskid'];

    $task = task::get_record(['id' => $taskid]);
    if (!empty($SESSION->deft_session)) {
        $peerid = $SESSION->deft_session->peerid;
        unset($SESSION->deft_session);
        venue_manager::close_peer($peerid);
    }

    $venue = new venue_manager($context, $task);
    $output = $PAGE->get_renderer('block_deft');

    $params = [
        'context' => $context,
        'objectid' => $taskid,
    ];

    $event = \block_deft\event\venue_started::create($params);
    $event->trigger();

    return $output->render($venue);
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function block_deft_get_path_from_pluginfile(string $filearea, array $args) : array {
    // This block stores files in venues that are identified by a task id.
    $taskid = array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => $taskid,
        'filepath' => $filepath,
    ];
}
