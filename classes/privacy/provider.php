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
 * Privacy Subsystem implementation for block_deft.
 *
 * @package    block_deft
 * @category   privacy
 * @copyright  2022 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\privacy;

use block_deft\task;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for block_deft.
 *
 * @copyright  2022 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_external_location_link('lti_client', [
            'context' => 'privacy:metadata:lti_client:context',
        ], 'privacy:metadata:lti_client');

        $collection->add_database_table(
            'block_deft',
            [
                'id' => 'privacy:metadata:block_deft:id',
                'usermodified' => 'privacy:metadata:block_deft:usermodified',
                'timemodified' => 'privacy:metadata:block_deft:timemodified',
            ],
            'privacy:metadata:block_deft'
        );

        $collection->add_database_table(
            'block_deft_room',
            [
                'itemid' => 'privacy:metadata:block_deft_room:itemid',
                'usermodified' => 'privacy:metadata:block_deft_room:usermodified',
                'timemodified' => 'privacy:metadata:block_deft_room:timemodified',
            ],
            'privacy:metadata:block_deft_room'
        );

        $collection->add_database_table(
            'block_deft_response',
            [
                'task' => 'privacy:metadata:block_deft_response:task',
                'response' => 'privacy:metadata:block_deft_response:response',
                'userid' => 'privacy:metadata:block_deft_response:userid',
                'timemodified' => 'privacy:metadata:block_deft_response:timemodified',
            ],
            'privacy:metadata:block_deft_response'
        );

        $collection->add_database_table(
            'block_deft_peer',
            [
                'task' => 'privacy:metadata:block_deft_peer:task',
                'userid' => 'privacy:metadata:block_deft_peer:userid',
                'timecreated' => 'privacy:metadata:block_deft_peer:timecreated',
                'timemodified' => 'privacy:metadata:block_deft_peer:timemodified',
                'mute' => 'privacy:metadata:block_deft_peer:mute',
                'status' => 'privacy:metadata:block_deft_peer:status',
                'type' => 'privacy:metadata:block_deft_peer:type',
                'uuid' => 'privacy:metadata:block_deft_peer:uuid',
            ],
            'privacy:metadata:block_deft_peer'
        );

        return $collection->add_subsystem_link('core_comment', [], 'privacy:metadata:core_comment');
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT contextid
                  FROM {comments}
                 WHERE component = :component
                   AND userid = :userid";
        $params = [
            'component' => 'block_deft',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_deft} t ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND t.usermodified = :userid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_deft} t ON t.instance = c.instanceid
                  JOIN {block_deft_room} r ON t.id = r.itemid
                 WHERE c.contextlevel = :contextlevel
                   AND r.component = 'block_deft'
                   AND r.usermodified = :userid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_deft} t ON t.instance = c.instanceid
                  JOIN {block_deft_peer} p ON t.id = p.taskid
                 WHERE c.contextlevel = :contextlevel
                   AND userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_deft} t ON t.instance = c.instanceid
                  JOIN {block_deft_response} r ON t.id = r.task
                 WHERE c.contextlevel = :contextlevel
                   AND userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $params = [
            'component' => 'block_deft',
            'contextid' => $context->id,
        ];

        $sql = "SELECT userid as userid
                  FROM {comments}
                 WHERE component = :component
                       AND contextid = :contextid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT t.usermodified
                  FROM {block_deft} t
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('usermodified', $sql, $params);

        $sql = "SELECT p.userid
                  FROM {block_deft_peer} p
                  JOIN {block_deft} t ON t.id = p.taskid
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT r.usermodified
                  FROM {block_deft_room} r
                  JOIN {block_deft} t ON t.id = r.itemid
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND r.component = 'block_deft'
                   AND c.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('usermodified', $sql, $params);

        $sql = "SELECT r.userid
                  FROM {block_deft_response} r
                  JOIN {block_deft} t ON t.id = r.task
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid";
        $params = [
            'contextlevel' => CONTEXT_BLOCK,
            'contextid' => $context->id,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();
        $contextids = [];
        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_BLOCK) {
                $contextids[] = $context->id;
            }
        }

        if (empty($contextids)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $tasks = task::get_records_select(
            "instance IN (
                SELECT bi.id
                  FROM {block_instances} bi
                  JOIN {context} c ON bi.id = c.instanceid
                 WHERE c.id $sql AND bi.blockname = 'deft'
            )",
            $params,
            'instance, type'
        );
        if (empty($tasks)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal(array_keys($tasks), SQL_PARAMS_NAMED);
        $responses = $DB->get_records_select(
            'block_deft_response',
            "task $sql AND userid = :userid",
            $params + [
                'userid' => $user->id,
            ],
            'task',
            'task, response, timemodified'
        );
        $peers = $DB->get_records_select(
            'block_deft_peer',
            "taskid $sql AND userid = :userid",
            $params + [
                'userid' => $user->id,
            ],
            'taskid',
            'id, taskid, mute, status, timecreated, timemodified, type, uuid'
        );
        foreach ($peers as $record) {
            $record->timecreated = \core_privacy\local\request\transform::datetime($record->timecreated);
            $record->timemodified = \core_privacy\local\request\transform::datetime($record->timemodified);
        }

        foreach ($tasks as $task) {
            $context = \context_block::instance($task->get('instance'));
            if ($task->get('type') == 'comment') {
                \core_comment\privacy\provider::export_comments(
                    $context,
                    'block_deft',
                    'task',
                    $task->get('id'),
                    [get_string('privacy:task', 'block_deft', $task->get('id'))]
                );
            } else if (key_exists($task->get('id'), $responses)) {
                $response = $responses[$task->get('id')];
                $response->timemodified = \core_privacy\local\request\transform::datetime($response->timemodified);
                writer::with_context($context)->export_data([
                    get_string('privacy:task', 'block_deft', $task->get('id')),
                    get_string('privacy:responses', 'block_deft'),
                ], (object)$response);
            } else if ($results = array_filter($peers, function($peer) use ($task) {
                return $peer->instance == $task->get('instance');
            })) {
                writer::with_context($context)->export_data([
                    get_string('privacy:task', 'block_deft', $task->get('id')),
                    get_string('privacy:connections', 'block_deft'),
                ], (object)$results);
            } else if ($task->get('usermodified') == $user->id) {
                $record = $task->to_record();
                $record->timemodified = \core_privacy\local\request\transform::datetime($record->timemodified);
                writer::with_context($context)->export_data([
                    get_string('privacy:tasks', 'block_deft'),
                ], (object)$record);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        \core_comment\privacy\provider::delete_comments_for_all_users($context, 'block_deft');

        $DB->delete_records_select(
            'block_deft_response',
            "task IN (
                SELECT t.id
                  FROM {block_deft} t
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid)",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'contextid' => $context->id,
            ]
        );

        $DB->delete_records_select(
            'block_deft_peer',
            "taskid IN (
                SELECT t.id
                  FROM {block_deft} t
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid)",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'contextid' => $context->id,
            ]
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'block_deft');

        $context = $userlist->get_context();

        if (!$context instanceof \context_block) {
            return;
        }

        $userids = $userlist->get_userids();
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'params', true, true);

        $DB->delete_records_select(
            'block_deft_response',
            "task IN (
                SELECT t.id
                  FROM {block_deft} t
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid
             ) AND userid $usersql",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'contextid' => $context->id,
            ] + $userparams
        );

        $DB->delete_records_select(
            'block_deft_peer',
            "taskid IN (
                SELECT t.id
                  FROM {block_deft} t
                  JOIN {context} c ON t.instance = c.instanceid
                 WHERE c.contextlevel = :contextlevel
                   AND c.id = :contextid
             ) AND userid $usersql",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'contextid' => $context->id,
            ] + $userparams
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'block_deft');

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $contextids = [];
        foreach ($contextlist->get_contexts() as $context) {
            $contextids[] = $context->id;
        }
        [$sql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $DB->delete_records_select(
            'block_deft_response',
            "task IN (
                 SELECT t.id
                   FROM {block_deft} t
                   JOIN {context} c ON t.instance = c.instanceid
                  WHERE c.contextlevel = :contextlevel
                    AND c.id $sql
             ) AND userid = :userid",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'userid' => $userid,
            ] + $params
        );

        $DB->delete_records_select(
            'block_deft_peer',
            "taskid IN (
                 SELECT t.id
                   FROM {block_deft} t
                   JOIN {context} c ON t.instance = c.instanceid
                  WHERE c.contextlevel = :contextlevel
                    AND c.id $sql
             ) AND userid = :userid",
            [
                'contextlevel' => CONTEXT_BLOCK,
                'userid' => $userid,
            ] + $params
        );
    }
}
