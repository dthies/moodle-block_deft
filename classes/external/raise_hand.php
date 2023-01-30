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
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function for logging hand raising events
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raise_hand extends \external_api {

    /**
     * Get parameter definition for raise hand
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'status' => new external_value(PARAM_BOOL, 'Whether hand should be raised'),
            ]
        );
    }

    /**
     * Log action
     *
     * @param int $status Whether to raise hand
     * @return array Status indicator
     */
    public static function execute($status): array {
        global $DB, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'status' => $status,
        ]);

        if (empty($SESSION->deft_session)) {
            return [
                'status' => false,
            ];
        }
        $task = $DB->get_record_select(
            'block_deft',
            'id IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
            [$SESSION->deft_session->peerid]
        );

        $context = context_block::instance($task->instance);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        $params = [
            'context' => $context,
            'objectid' => $task->id,
        ];

        if ($status) {
            $event = \block_deft\event\hand_raise_sent::create($params);
        } else {
            $event = \block_deft\event\hand_lower_sent::create($params);
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
