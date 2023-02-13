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
use context;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function for getting new token
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_signal extends \external_api {

    /**
     * Get parameter definition for send_signal.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'Context id of block'),
                'messages' => new external_multiple_structure(
                    new external_single_structure([
                        'peerid' => new external_value(PARAM_INT, 'Peer id of recipient'),
                        'message' => new external_value(PARAM_TEXT, 'Message content in JSON'),
                        'type' => new external_value(PARAM_TEXT, 'Message type'),
                    ]),
                ),
                'lastsignal' => new external_value(PARAM_INT, 'Last signal received through previous call'),
            ]
        );
    }

    /**
     * Store message to be delivered
     *
     * @param int $contextid The block context id
     * @param array $messages Array of messages to other peers
     * @param int $lastsignal Last signal received
     * @return array Messages from other peers
     */
    public static function execute($contextid, $messages, $lastsignal): array {
        global $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'messages' => $messages,
            'lastsignal' => $lastsignal,
        ]);

        $context = context::instance_by_id($contextid);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        foreach ($messages as $message) {
            venue_manager::send_signal($message['peerid'], $message['message'], $message['type']);
        }

        if (count($messages)) {
            $socket = new socket($context);
            $socket->dispatch();
        }

        $messages = venue_manager::receive_signals($lastsignal);
        $peers = venue_manager::peer_connections();
        $settings = venue_manager::settings();

        return [
            'messages' => $messages,
            'peers' => $peers,
            'settings' => $settings,
        ];
    }

    /**
     * Get return definition for send_signal
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Message id if successful'),
                    'frompeer' => new external_value(PARAM_INT, 'Sender id'),
                    'message' => new external_value(PARAM_TEXT, 'Message JSON'),
                    'type' => new external_value(PARAM_TEXT, 'Message type'),
                ]),
            ),
            'peers' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Currently available peer id'),
            ),
            'settings' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Current peer id'),
                    'mute' => new external_value(PARAM_BOOL, 'Whether audio should be muted'),
                    'status' => new external_value(PARAM_BOOL, 'Whether connection should be closed'),
                ]),
            ),
        ]);
    }
}
