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
 * Class managing meeting in Deft response block
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft;

use cache;
use context_block;
use core_user;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use block_deft\socket;
use block_deft\task;
use user_picture;


/**
 * Class managing meeting in Deft response block
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class venue_manager implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param context_block $context The context of the block.
     * @param task $task The task that defines the venue
     */
    public function __construct(context_block $context, task $task) {
        global $DB, $SESSION, $USER;

        $this->context = $context;
        $this->task = $task;
        $this->socket = new socket($context);

        if (empty($SESSION->deft_session)) {
            $SESSION->deft_session = (object) [
                'lastmodified' => time(),
                'userid' => $USER->id,
                'taskid' => $this->task->get('id'),
                'timecreated' => time(),
            ];
            $sessionid = $DB->insert_record('block_deft_peer', $SESSION->deft_session);
            $SESSION->deft_session->peerid = $sessionid;
            $this->socket->dispatch();
        } else {
            $sessionid = $SESSION->deft_session->peerid;
        }

        $this->messages = $DB->get_records('block_deft_signal', [
            'topeer' => $sessionid,
        ], 'id');

        $this->sessions = $DB->get_records_sql('
            SELECT p.id AS sessionid, u.*
               FROM {block_deft_peer} p
               JOIN {user} u ON u.id = p.userid
              WHERE p.taskid = :taskid
                    AND p.status = 0', [
            'taskid' => $this->task->get('id'),
        ]);
        unset($this->sessions[$sessionid]);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE, $SESSION, $USER;

        $url = new moodle_url('/blocks/deft/venue.php', ['id' => $this->task->get('id')]);

        $user = clone ($USER);
        $user->fullname = fullname($user);
        $userpicture = new user_picture($user);
        $user->pictureurl = $userpicture->get_url($PAGE, $output);
        $user->avatar = $output->user_picture($user, [
            'class' => 'card-img-top',
            'link' => false,
            'size' => 256,
        ]);
        return [
            'canmanage' => has_capability('block/deft:manage', $this->context),
            'contextid' => $this->context->id,
            'iceservers' => json_encode($this->socket->ice_servers()),
            'throttle' => get_config('block_deft', 'throttle'),
            'peerid' => $SESSION->deft_session->peerid,
            'peers' => json_encode(array_keys($this->sessions)),
            'sessions' => array_values($this->sessions),
            'token' => $this->socket->get_token(),
            'uniqueid' => uniqid(),
            'url' => $url->out(true),
            'user' => $user,
        ];
    }

    /**
     * Save signal to be sent from peer to peer
     *
     * @param int $peerid Peer id of recipient
     * @param string $message Signal message in JSON
     * @param string $type Signal type
     * @return int Record id
     */
    public static function send_signal(int $peerid, string $message, string $type): int {
        global $DB, $SESSION, $USER;

        $senderid = $SESSION->deft_session->peerid;

        return $DB->insert_record('block_deft_signal', [
            'frompeer' => $senderid,
            'message' => $message,
            'timecreated' => time(),
            'topeer' => $peerid,
            'type' => $type,
        ]);
    }

    /**
     * Retrieve signals sent to peer
     *
     * @param int $lastsignal Last signal successfully retrieved
     * @return array
     */
    public static function receive_signals(int $lastsignal): array {
        global $DB, $SESSION, $USER;

        $peerid = $SESSION->deft_session->peerid;

        // Delete old records.
        $DB->delete_records_select('block_deft_signal', 'topeer = :topeer AND id <= :lastsignal', [
            'topeer' => $peerid,
            'lastsignal' => $lastsignal,
        ]);

        return $DB->get_records('block_deft_signal', [
            'topeer' => $peerid,
        ], 'id', 'id, frompeer, message, type');
    }

    /**
     * Get settings for all peers
     *
     * @return array
     */
    public static function settings(): array {
        global $DB, $SESSION, $USER;

        $peerid = $SESSION->deft_session->peerid;

        $settings = $DB->get_records_select(
            'block_deft_peer',
            'taskid IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
            [$peerid],
            '',
            'id, mute, status'
        );

        return $settings;
    }

    /**
     * Delete pear records
     *
     * @param int $peerid In of peer to remove
     */
    public static function delete_peer(int $peerid) {
        global $DB;

        $DB->delete_records('block_deft_signal', ['topeer' => $peerid]);
        $DB->delete_records('block_deft_signal', ['frompeer' => $peerid]);
        $DB->delete_records('block_deft_peer', ['id' => $peerid]);
    }
}
