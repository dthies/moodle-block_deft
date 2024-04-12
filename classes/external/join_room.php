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

use block_deft\janus;
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
 * External function for joining Janus gateway
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join_room extends external_api {
    /**
     * Get parameter definition for raise hand
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'handle' => new external_value(PARAM_INT, 'Plugin handle id'),
                'id' => new external_value(PARAM_INT, 'Peer id for user session'),
                'plugin' => new external_value(PARAM_TEXT, 'Janus plugin name'),
                'ptype' => new external_value(PARAM_BOOL, 'Whether video pubisher', VALUE_DEFAULT, false),
                'room' => new external_value(PARAM_INT, 'Room id being joined'),
                'session' => new external_value(PARAM_INT, 'Janus session id'),
                'feed' => new external_value(PARAM_INT, 'Initial feed', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Join room
     *
     * @param int $handle Janus plugin handle
     * @param string $id Venue peer id
     * @param int $plugin Janus plugin name
     * @param bool $ptype Whether video publisher
     * @param int $room Room id being joined
     * @param int $session Janus session id
     * @param int $feed Initial video feed
     * @return array
     */
    public static function execute($handle, $id, $plugin, $ptype, $room, $session, $feed): array {
        global $DB, $SESSION, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'handle' => $handle,
            'id' => $id,
            'plugin' => $plugin,
            'ptype' => $ptype,
            'room' => $room,
            'session' => $session,
            'feed' => $feed,
        ]);

        $task = $DB->get_record_select(
            'block_deft',
            'id IN (SELECT taskid FROM {block_deft_peer} WHERE id = ? AND status = 0)',
            [$id]
        );

        $context = context_block::instance($task->instance);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        $janus = new janus($session);

        $token = $DB->get_field('block_deft_room', 'token', [
            'roomid' => $room,
            'itemid' => $task->id,
            'component' => 'block_deft',
        ]);

        if ($plugin == 'janus.plugin.videoroom') {
            $message = [
                'ptype' => $ptype ? 'publisher' : 'subscriber',
                'request' => 'join',
                'room' => $room,
                'token' => $token,
            ];
            if ($feed) {
                $message['streams'] = [
                    [
                        'feed' => $feed,
                    ],
                ];
            } else {
                require_capability('block/deft:sharevideo', $context);
                $DB->set_field('block_deft_peer', 'status', 1, [
                    'type' => 'video',
                    'taskid' => $task->id,
                ]);
                $feedid = $DB->insert_record('block_deft_peer', [
                    'sessionid' => $DB->get_field('sessions', 'id', [
                        'sid' => session_id(),
                    ]),
                    'taskid' => $task->id,
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'type' => 'video',
                    'userid' => $USER->id,
                ]);
                $message['id'] = $feedid;
            }
        } else {
            $message = [
                'id' => $id,
                'request' => 'join',
                'room' => $room,
                'token' => $token,
            ];
            $params = [
                'context' => $context,
                'objectid' => $task->id,
            ];

            $event = \block_deft\event\audiobridge_launched::create($params);
            $event->trigger();
        }

        $janus->send($handle, $message);

        return [
            'status' => true,
            'id' => (int) $feedid ?? 0,
        ];
    }

    /**
     * Get return definition for hand_raise
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether successful'),
            'id' => new external_value(PARAM_INT, 'New video session id'),
        ]);
    }
}
