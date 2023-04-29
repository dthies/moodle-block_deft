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
use moodle_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use block_deft\socket;
use block_deft\task;
use user_picture;


/**
 * Class managing venue in Deft response block
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

        if (!empty($SESSION->deft_session)) {
            $peerid = $SESSION->deft_session->peerid;
        } else if ($this->can_access()) {
            $SESSION->deft_session = (object) [
                'lastmodified' => time(),
                'userid' => $USER->id,
                'taskid' => $this->task->get('id'),
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $sessionid = $DB->get_field('sessions', 'id', ['sid' => session_id()]);
            $DB->set_field('block_deft_peer', 'sessionid', 1, ['sessionid' => $sessionid]);
            $peerid = $DB->insert_record('block_deft_peer', [
                'sessionid' => $sessionid,
            ] + (array)$SESSION->deft_session);
            $SESSION->deft_session->peerid = $peerid;
            $this->socket->dispatch();
        } else {
            return;
        }

        $this->messages = $DB->get_records('block_deft_signal', [
            'topeer' => $peerid,
        ], 'id');

        $this->sessions = $DB->get_records_sql('
            SELECT p.id AS sessionid, u.*
               FROM {block_deft_peer} p
               JOIN {user} u ON u.id = p.userid
               JOIN {sessions} ss ON p.sessionid = ss.id
              WHERE p.taskid = :taskid
                    AND p.status = 0', [
            'taskid' => $this->task->get('id'),
        ]);
        unset($this->sessions[$peerid]);
    }

    /**
     * Find other peers which should be connected currently
     *
     * @return array
     */
    public static function peer_connections(): array {
        global $DB, $SESSION;

        return $DB->get_fieldset_select(
            'block_deft_peer',
            'id',
            'status = 0 AND taskid IN (SELECT taskid FROM {block_deft_peer} WHERE id = ?)',
            [$SESSION->deft_session->peerid]
        );
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE, $SESSION, $USER;

        if (empty($SESSION->deft_session)) {
            return [];
        }

        $url = new moodle_url('/blocks/deft/venue.php', ['id' => $this->task->get('id')]);

        $user = clone ($USER);
        $user->fullname = fullname($user);
        $userpicture = new user_picture($user);
        $user->pictureurl = $userpicture->get_url($PAGE, $output);
        $user->avatar = $output->user_picture($user, [
            'class' => 'card-img-top p-1 m-1',
            'link' => false,
            'size' => 36,
        ]);

        $config = $this->task->get_config();
        list($roomid, $roomtoken, $server) = $this->get_room();

        return [
            'autogaincontrol' => !empty(get_config('block_deft', 'autogaincontrol')),
            'canmanage' => has_capability('block/deft:moderate', $this->context),
            'contextid' => $this->context->id,
            'echocancellation' => !empty(get_config('block_deft', 'echocancellation')),
            'enablevideo' => true,
            'iceservers' => json_encode($this->socket->ice_servers()),
            'intro' => format_text(
                file_rewrite_pluginfile_urls(
                    $config->intro->text ?? '',
                    'pluginfile.php',
                    $this->context->id,
                    'block_deft',
                    'venue',
                    $this->task->get('id')
                ),
                $config->intro->format ?? FORMAT_MOODLE,
                ['context' => $this->context]
            ),
            'noisesuppression' => !empty(get_config('block_deft', 'noisesuppression')),
            'throttle' => get_config('block_deft', 'throttle'),
            'peerid' => $SESSION->deft_session->peerid,
            'peerconnection' => ($config->connection ?? 'peer') == 'peer',
            'peers' => json_encode(array_keys($this->sessions)),
            'popup' => !isset($this->task->get_config()->windowoption) || $this->task->get_config()->windowoption != 'openinwindow',
            'samplerate' => get_config('block_deft', 'samplerate'),
            'roomid' => $roomid,
            'server' => $server,
            'sessions' => array_values($this->sessions),
            'sharevideo' => has_capability('block/deft:sharevideo', $this->context)
            && !empty($config->connection)
            && ('peer' != $config->connection)
            && get_config('block_deft', 'enablebridge')
            && get_config('block_deft', 'enablevideo'),
            'taskid' => $this->task->get('id'),
            'token' => $this->socket->get_token(),
            'title' => format_string($this->task->get_config()->name),
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

        return $DB->get_records_sql('
            SELECT s.id, s.frompeer, s.message, s.type
              FROM {block_deft_signal} s
              JOIN {block_deft_peer} p ON p.id = s.frompeer
              JOIN {sessions} ss ON p.sessionid = ss.id
             WHERE s.topeer = :topeer AND p.status = 0
          ORDER BY id',
            [
                'topeer' => $peerid,
            ]
        );
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
     * Handle a logout event
     *
     * @param \core\event\base $event
     */
    public static function logout(\core\event\base $event) {
        global $SESSION;

        if (!empty($SESSION->deft_session) && $record = $DB->get_record('block_deft_peer', [
            'id' => $SESSION->deft->peerid,
            'status' => 0,
        ])) {
            $eventdata = $event->get_data();
            $record->status = 1;
            $DB->update_record('block_deft_peer', $record);
        }
    }

    /**
     * Close peer and cleanup records
     *
     * @param int $peerid In of peer to remove
     */
    public static function close_peer(int $peerid) {
        global $DB;

        $DB->delete_records('block_deft_signal', ['topeer' => $peerid]);
        $DB->delete_records('block_deft_signal', ['frompeer' => $peerid]);
        $DB->update_record('block_deft_peer', [
            'id' => $peerid,
            'timemodified' => time(),
            'status' => true,
        ]);
    }

    /**
     * Check whether current user can access venue
     *
     * @return boolean
     */
    public function can_access() {
        global $DB;
        if (has_capability('block/deft:moderate', $this->context)) {
            return true;
        } else if (
            !empty($this->task->get_state()->close)
            || !has_capability('block/deft:joinvenue', $this->context)
        ) {
            return false;
        } else if (empty($this->task->get_config()->limit)) {
            return true;
        }

        return $this->task->get_config()->limit > count($DB->get_fieldset_select(
            'block_deft_peer',
            'id',
            'status = 0 AND taskid = ?',
            [$this->task->get('id')]
        ));
    }

    /**
     * Return room information for task
     *
     * @return array
     */
    protected function get_room() {
        global $SESSION;

        if (($this->task->get_config()->connection ?? 'peer') == 'peer') {
            return [0, '', ''];
        }

        try {
            $room = new \block_deft\janus_room($this->task);
        } catch (moodle_exception $e) {
            return [0, '', ''];
        }

        $token = $room->get_token();

        $SESSION->deft_session->token = $token;

        return [$room->get_roomid(), $room->get_token(), $room->get_server()];
    }
}
