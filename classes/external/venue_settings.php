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
use context_block;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function for getting new token
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class venue_settings extends \external_api {

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
            ]
        );
    }

    /**
     * Change settings
     *
     * @param int $mute Whether to mute
     * @param int $status Whether to close
     * @return array Status indicator
     */
    public static function execute($mute, $status): array {
        global $DB, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'mute' => $mute,
            'status' => $status,
        ]);

        if (empty($SESSION->deft_session)) {
            return [
                'status' => false,
            ];
        }

        $blockid = $DB->get_field_select(
            'block_deft',
            'instance',
            'id IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
            [$SESSION->deft_session->peerid]
        );

        $context = context_block::instance($blockid);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        $DB->update_record('block_deft_peer', [
            'id' => $SESSION->deft_session->peerid,
            'mute' => $mute,
            'status' => $status,
            'timemodified' => time(),
        ]);

        $SESSION->deft_session->lastmodified = time();

        $socket = new socket($context);
        $socket->dispatch();

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
