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
use block_deft\task;
use block_deft\venue_manager;
use cache;
use context;
use context_block;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;

/**
 * External function to offer feed to venue
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_feed extends external_api {

    /**
     * Get parameter definition for raise hand
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Peer id for user session'),
                'publish' => new external_value(PARAM_BOOL, 'Whether to publish or not', VALUE_DEFAULT, true),
                'room' => new external_value(PARAM_INT, 'Room id being joined'),
            ]
        );
    }

    /**
     * Publish feed
     *
     * @param string $id Venue peer id
     * @param bool $publish Whether to publish
     * @param int $room Room id being joined
     * @return array
     */
    public static function execute($id, $publish, $room): array {
        global $DB, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'publish' => $publish,
            'room' => $room,
        ]);

        if (empty($SESSION->deft_session)) {
            return [
                'status' => false,
            ];
        }
        $record = $DB->get_record_select(
            'block_deft',
            'id IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
            [$SESSION->deft_session->peerid]
        );

        $context = context_block::instance($record->instance);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);
        if ($publish) {
            require_capability('block/deft:sharevideo', $context);
        }

        $task = new task();
        $task->from_record($record);

        $record = $DB->get_record('block_deft_room', [
            'roomid' => $room,
            'component' => 'block_deft',
        ]);
        $data = json_decode($record->data) ?? new stdClass();
        if (!$publish && !empty($data->feed) && $DB->get_record_select(
            'block_deft_peer',
            "type = 'video' AND id = :feed",
            ['feed' => $data->feed]
        )) {
            $DB->set_field('block_deft_peer', 'status', 1, [
                'id' => $data->feed,
            ]);
            $data->feed = 0;
        } else if ($publish) {
            if (
                !empty($data->feed)
                && ($data->feed != $id)
                && $DB->get_record('block_deft_peer', [
                    'id' => $data->feed,
                    'status' => 0,
                    'type' => 'video',
                ])
            ) {
                require_capability('block/deft:moderate', $context);
            }
            $data->feed = $DB->get_field_select(
                'block_deft_peer',
                'id',
                "status = 0
                  AND type = 'video'
                  AND sessionid IN (SELECT id FROM {sessions}
                                     WHERE sid = ?
                                           AND status = 0)",
                [session_id()]
            );
        } else {
            return [
                'status' => false,
            ];
        }

        $record->timemodified = time();
        $record->data = json_encode($data);
        $DB->update_record('block_deft_room', $record);
        $task->clear_cache();
        $socket = new socket($context);
        $socket->dispatch();

        $params = [
            'context' => $context,
            'objectid' => $task->get('id'),
        ];

        if ($publish) {
            $event = \block_deft\event\video_started::create($params);
        } else {
            $event = \block_deft\event\video_ended::create($params);
        }
        $event->trigger();

        return [
            'status' => true,
        ];
    }

    /**
     * Get return definition for hand_raise
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether changed'),
        ]);
    }
}
