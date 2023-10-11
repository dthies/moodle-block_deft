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

namespace block_deft\external;

use block_deft\socket;
use block_deft\venue_manager;
use cache;
use context;
use context_block;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function for storing user venue settings
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class venue_settings extends external_api {

    /**
     * Get parameter definition for send_signal.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'mute' => new external_value(PARAM_BOOL, 'Whether audio should be muted'),
                'status' => new external_value(PARAM_BOOL, 'Whether the connection should be closed'),
                'peerid' => new external_value(PARAM_INT, 'Some other peer to change', VALUE_DEFAULT, 0),
                'uuid' => new external_value(PARAM_TEXT, 'Unique identifier for app users', VALUE_DEFAULT, ''),
            ]
        );
    }

    /**
     * Change settings
     *
     * @param bool $mute Whether to mute
     * @param bool $status Whether to close
     * @param int $peerid The id of a user's peer changed by manager
     * @param string $uuid Device id for mobile app
     * @return array Status indicator
     */
    public static function execute($mute, $status, $peerid, $uuid): array {
        global $DB, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'mute' => $mute,
            'status' => $status,
            'peerid' => $peerid,
            'uuid' => $uuid,
        ]);

        if (
            (empty($SESSION->deft_session) || !$task = $DB->get_record_select(
                'block_deft',
                'id IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
                [$SESSION->deft_session->peerid]
            )) && (empty($uuid) || !$task = $DB->get_record_select(
                'block_deft',
                'id IN (SELECT taskid FROM {block_deft_peer} WHERE uuid = ?)',
                [$uuid]
            ))
        ) {
            return [
                'status' => false,
            ];
        }

        $context = context_block::instance($task->instance);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        if (!empty($SESSION->deft_session->peerid)) {
            if (!empty($peerid) && $peerid != $SESSION->deft_session->peerid) {
                require_capability('block/deft:moderate', $context);
                $relateduserid = $DB->get_field('block_deft_peer', 'userid', ['id' => $peerid]);
            } else {
                $peerid = $SESSION->deft_session->peerid;
            }
        } else if (!empty($uuid)) {
            $peerid = $DB->get_field('block_deft_peer', 'id', [
                'uuid' => $uuid,
                'status' => false,
            ]);
        }

        if ($record = $DB->get_record('block_deft_peer', [
            'id' => $peerid,
            'mute' => $mute,
            'status' => $status,
        ])) {
            // No changes needed.
            return [
                'status' => false,
            ];
        }

        $DB->update_record('block_deft_peer', [
            'id' => $peerid,
            'mute' => $mute,
            'status' => $status,
            'timemodified' => time(),
        ]);

        $cache = cache::make('block_deft', 'tasks');
        $cache->delete($task->instance);

        $socket = new socket($context);
        $socket->dispatch();

        $params = [
            'context' => $context,
            'objectid' => $task->id,
        ];

        if ($status && $feed = $DB->get_record_sql("
            SELECT f.*
              FROM {block_deft_peer} f
              JOIN {block_deft_peer} p ON p.sessionid = f.sessionid AND p.uuid = f.uuid
             WHERE f.type = 'video' AND p.id = ?", [$peerid])) {
            $feed->status = $status;
            $feed->timemodified = time();
            $DB->update_record('block_deft_peer', $feed);

            if ($room = $DB->get_record('block_deft_room', [
                'itemid' => $task->id,
                'component' => 'block_deft',
            ])) {
                $data = json_decode($room->plugindata);
                $data->feed = 0;
                $room->plugindata = json_encode($data);
                $room->timemodified = time();
                $DB->update_record('block_deft_room', $feed);
            }
        }

        if ($status) {
            $event = \block_deft\event\venue_ended::create($params);
        } else {
            $params['other'] = ['status' => $mute];
            if (!empty($relateduserid)) {
                $params['relateduserid'] = $relateduserid;
            }
            $event = \block_deft\event\mute_switched::create($params);
        }
        $event->trigger();

        return [
            'status' => true,
        ];
    }

    /**
     * Get return definition for send_signal
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether changed'),
        ]);
    }
}
