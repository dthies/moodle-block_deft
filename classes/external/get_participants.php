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
use block_deft\task;
use block_deft\venue_manager;
use context;
use context_block;
use core_user;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_api;
use moodle_url;
use user_picture;

/**
 * External function to get venue participants
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_participants extends external_api {
    /**
     * Get parameter definition for send_signal.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'taskid' => new external_value(PARAM_INT, 'Task Id of venue'),
            ]
        );
    }

    /**
     * Get venue participant information
     *
     * @param int $taskid The task id of venue
     * @return array Messages from other peers
     */
    public static function execute($taskid): array {
        global $DB, $PAGE, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'taskid' => $taskid,
        ]);

        $task = $DB->get_record('block_deft', ['id' => $taskid]);
        $context = context_block::instance($task->instance);
        $PAGE->set_context($context);
        self::validate_context($context);

        require_login();
        require_capability('block/deft:joinvenue', $context);

        $participants = $DB->get_records('block_deft_peer', [
            'taskid' => $taskid,
            'type' => 'venue',
        ], '', 'id, userid, mute, status');

        $data = json_decode($DB->get_field(
            'block_deft_room',
            'data',
            [
                'itemid' => $taskid,
                'component' => 'block_deft',
            ]
        ));

        foreach ($participants as $participant) {
            $user = core_user::get_user($participant->userid);
            $userpicture = new user_picture($user);
            $output = $PAGE->get_renderer('block_deft');
            $participant->content = $output->render_from_template('block_deft/mobile_venue_participant', [
                'fullname' => fullname($user),
                'pictureurl' => $userpicture->get_url($PAGE, $output),
                'peerid' => $participant->id,
            ]);
        }

        return [
            'feed' => $data->feed ?? '',
            'participants' => $participants,
        ];
    }

    /**
     * Get return definition for send_signal
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feed' => new external_value(PARAM_TEXT, 'Published video feed'),
            'participants' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Current peer id'),
                    'content' => new external_value(PARAM_RAW, 'Rendered content to display for participant'),
                    'mute' => new external_value(PARAM_BOOL, 'Whether audio should be muted'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'status' => new external_value(PARAM_BOOL, 'Whether connection should be closed'),
                ]),
            ),
        ]);
    }
}
