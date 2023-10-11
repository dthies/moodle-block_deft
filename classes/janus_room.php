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
 * Janus room handler
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

use cache;
use context;
use moodle_exception;
use stdClass;

/**
 * Janus room handler
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class janus_room {

    /**
     * @var Endpoint Server endpoint
     */
    protected const ENDPOINT = 'https://deftly.us/admin/tool/deft/message.php';

    /**
     * @var Plugin component using room
     */
    protected string $component = 'block_deft';

    /**
     * @var item id
     */
    protected int $itemid = 0;

    /**
     * @var Audio bridge plugin handle id
     */
    protected int $audiobridge = 0;

    /**
     * @var ID for room
     */
    protected int $roomid = 0;

    /**
     * @var Text room plugin handle id
     */
    protected int $textroom = 0;

    /**
     * @var Authorization token
     */
    protected string $token = '';

    /**
     * @var Secret
     */
    protected string $secret = '';

    /**
     * @var Server
     */
    protected string $server = '';

    /**
     * @var Janus session web client
     */
    protected $session = null;

    /**
     * @var Video room plugin handle id
     */
    protected int $videoroom = 0;

    /**
     * Constructor
     *
     * @param task $task Task associated with venue
     */
    public function __construct (task $task) {
        global $DB, $USER;

        if (
            !get_config('block_deft', 'enablebridge')
            || ($task->get_config()->connection !== 'mixed')
        ) {
            return;
        }

        $this->session = new janus();
        $this->task = $task;
        $this->itemid = $task->get('id');

        if (!$record = $DB->get_record('block_deft_room', [
            'component' => $this->component,
            'itemid' => $this->itemid,
        ])) {
            $records = $DB->get_records('block_deft_room', ['itemid' => null]);
            if ($record = reset($records)) {
                $record->itemid = $this->itemid;
                $record->component = $this->component;
                $record->usermodified = $USER->id;
                $record->timemodified = time();
                $DB->update_record('block_deft_room', $record);
            } else {
                $this->create_room();
                $record = $this->record;
            }
        }

        $this->record = $record;

        $this->roomid = $record->roomid ?? 0;
        $this->secret = $record->secret ?? '';
        $this->server = $record->server ?? '';
        $this->token = $record->token ?? '';
        $this->session = new janus();
        $this->textroom = $this->session->attach('janus.plugin.textroom');

        $this->init_room();
    }

    /**
     * Check room availabity and create if necessary
     */
    protected function init_room() {
        $exists = [
            'request' => 'exists',
            'room' => $this->roomid,
        ];

        $response = $this->textroom_send($exists);
        if (!$response->plugindata->data->exists) {
            return $this->create_room();
        }

        $response = $this->audiobridge_send($exists);
        if (!$response->plugindata->data->exists) {
            return $this->create_room();
        }

        $response = $this->videoroom_send($exists);
        if (!$response->plugindata->data->exists) {
            return $this->create_room();
        }

        $this->set_token();
    }

    /**
     * Create an initialize room on media server
     */
    protected function create_room() {
        global $DB, $USER;

        $roomids = $DB->get_fieldset_select('block_deft_room', 'roomid', 'itemid IS NOT NULL', []);

        $requestparams = [
            'action' => 'room',
            'reserved' => $roomids,
            'roomid' => $this->roomid,
            'secret' => $this->secret,
            'contextid' => 0,
        ];

        $clientid = $DB->get_field_select('lti_types', 'clientid', "tooldomain = 'deftly.us'");

        $jwt = lti_sign_jwt($requestparams, self::ENDPOINT, $clientid);

        $requestparams = array_merge($requestparams, $jwt);

        $query = html_entity_decode(http_build_query($requestparams), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);

        $response = json_decode(file_get_contents(
            self::ENDPOINT . '?' . $query
        ));

        if (!$response) {
            return;
        } else if (empty($response->roomid)) {
            return;
        }

        $this->secret = $response->secret;
        $this->server = $response->server;
        $this->roomid = $response->roomid;

        if (
            ($record = $DB->get_record('block_deft_room', [
                'component' => $this->component,
                'itemid' => $this->itemid,
            ]))
            ||
            ($record = $DB->get_record('block_deft_room', [
                'roomid' => $response->roomid,
            ]))
        ) {
            $record->secret = $response->secret;
            $record->roomid = $response->roomid;
            $record->server = $response->server;
            $record->itemid = $this->itemid;
            $record->component = $this->component;
            $record->plugindata = '{}';
            $record->timemodified = time();
            $DB->update_record('block_deft_room', $record);
            $this->record = $record;
        } else {
            $this->record = new stdClass();
            $this->record->secret = $response->secret;
            $this->record->server = $response->server;
            $this->record->roomid = $response->roomid;
            $this->record->itemid = $this->itemid;
            $this->record->component = $this->component;
            $this->record->plugindata = '{}';
            $this->record->timecreated = time();
            $this->record->timemodified = $this->record->timecreated;
            $this->record->usermodified = $USER->id;
            $this->record->id = $DB->insert_record('block_deft_room', $this->record);
        }

        $this->set_token();

        return;
    }

    /**
     * Assign token for venue
     */
    protected function set_token() {
        global $DB;

        if (empty($this->token)) {
            $this->token = $this->session->transaction_identifier();

            $DB->set_field('block_deft_room', 'token', $this->token, [
                'roomid' => $this->roomid,
            ]);
        }

        $allow = [
            'request' => 'allowed',
            'room' => $this->roomid,
            'secret' => $this->secret,
            'action' => 'add',
            'allowed' => [$this->token],
        ];
        $response = $this->audiobridge_send($allow);
        if (!empty($response->plugindata->data->error)) {
            return;
        }
        $response = $this->videoroom_send($allow);
        if (!empty($response->plugindata->data->error)) {
            return;
        }
    }

    /**
     * Get room id
     *
     * @return int
     */
    public function get_roomid(): int {
        return $this->roomid;
    }

    /**
     * Get room secret
     *
     * @return int
     */
    public function get_secret(): string {
        return $this->secret;
    }

    /**
     * Get room server
     *
     * @return string
     */
    public function get_server(): string {
        return $this->server;
    }

    /**
     * Get authorization token
     *
     * @return string
     */
    public function get_token(): string {
        return $this->token;
    }

    /**
     * Send message to audio bridge plugin
     *
     * @param array|stdClass $message
     * @return stdClass
     */
    public function audiobridge_send($message) {
        if (!$this->audiobridge) {
            $this->audiobridge = $this->session->attach('janus.plugin.audiobridge');
        }

        return $this->session->send($this->audiobridge, $message);
    }

    /**
     * Send message to text room plugin
     *
     * @param array|stdClass $message
     * @return stdClass
     */
    public function textroom_send($message) {
        return $this->session->send($this->textroom, $message);
    }

    /**
     * Send message to video room plugin
     *
     * @param array|stdClass $message
     * @return stdClass
     */
    public function videoroom_send($message) {
        if (!$this->videoroom) {
            $this->videoroom = $this->session->attach('janus.plugin.videoroom');
        }

        return $this->session->send($this->videoroom, $message);
    }

    /**
     * Unassign room from task
     *
     * @param string $component Component
     * @param int $itemid Item id
     * @return stdClass
     */
    public static function remove($component, $itemid) {
        global $DB, $USER;

        if ($record = $DB->get_record('block_deft_room', [
            'component' => $component,
            'itemid' => $itemid,
        ])) {
            $record->itemid = null;
            $record->component = '';
            $record->timemodified = time();
            $record->usermodified = $USER->id;
            $DB->update_record('block_deft_room', $record);
        }
    }

    /**
     * Query server for participants
     *
     * @return stdClass
     */
    public function list_participants() {
        return $this->audiobridge_send([
            'request' => 'listparticipants',
            'room' => $this->roomid,
        ]);
    }
}
