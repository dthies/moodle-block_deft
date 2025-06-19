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

namespace block_deft\output;

use block_deft\socket;
use block_deft\task;
use cache;
use context;
use moodle_exception;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Jitsi venue handler
 *
 * @package    block_deft
 * @copyright  2025 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jitsi_room implements renderable, templatable {
    /** @var $context The context of the block */
    protected $context = null;

    /** @var $socket Socket object */
    protected $socket = null;

    /** @var $task Task configuration */
    protected $task = null;

    /** @var $record Room database record */
    protected $record = null;

    /**
     * @var Plugin component using room
     */
    protected string $component = 'block_deft';

    /**
     * @var item id
     */
    protected int $itemid = 0;

    /**
     * @var ID for room
     */
    protected int $roomid = 0;

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
     * Constructor
     *
     * @param task|null $task Task associated with venue
     * @param context|null $context Block context
     */
    public function __construct(?task $task = null, $context = null) {
        global $DB, $USER;

        if (empty($context)) {
            $this->context = \core\context\block::instance($task->get('instance'));
        } else {
            $this->context = $context;
        }

        if ($task) {
            $this->task = $task;
            $this->itemid = $task->get('id');
        }
    }

    /**
     * Return the room key
     *
     * @return string
     */
    public function get_room() {

        return "blockdeft{$this->context->instanceid}" . ($this->task ? "task{$this->itemid}" : '');
    }

    /**
     * Return the server host
     *
     * @return string
     */
    public function get_server() {
        return get_config('block_deft', 'jitsiserver');
    }

    /**
     * Return the jwt
     *
     * @return string
     */
    public function get_jwt() {
        global $USER;

        $header = json_encode([
            "alg" => "HS256",
            "kid" => "jitsi2/custom_key_name",
            "typ" => "JWT",
        ], JSON_UNESCAPED_SLASHES);
        $payload = json_encode([
            'aud' => 'jitsi2',
            'context' => [
                'user' => [
                    'id' => $USER->username,
                    'name' => fullname($USER),
                    'email' => $USER->email,
                ],
            ],
            'exp' => time() + DAYSECS,
            'iss' => get_config('block_deft', 'appid'),
            'moderator' => has_capability('block/deft:moderate', $this->context),
            'sub' => get_config('block_deft', 'jitsiserver'),
            'room' => $this->get_room(),
        ], JSON_UNESCAPED_SLASHES);
        $message = $this->encode($header) . '.' . $this->encode($payload);
        return $message . '.' . $this->encode(hash_hmac('SHA256', $message, get_config('block_deft', 'secret'), true));
    }

    /**
     * Encode content for jwt
     *
     * @param string $content
     * @return string
     */
    protected function encode($content) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($content));
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $SESSION, $USER;

        $config = $this->task->get_config();

        return [
            'canmanage' => has_capability('block/deft:moderate', $this->context),
            'contextid' => $this->context->id,
            'email' => $USER->email,
            'enablevideo' => get_config('block_deft', 'enablevideo'),
            'fullname' => fullname($USER),
            'jwt' => $this->get_jwt(),
            'throttle' => get_config('block_deft', 'throttle'),
            'peerid' => $SESSION->deft_session->peerid,
            'peers' => '[]',
            'room' => $this->get_room(),
            'server' => get_config('block_deft', 'jitsiserver'),
            'sharevideo' => has_capability('block/deft:sharevideo', $this->context)
            && !empty($config->connection)
            && ('peer' != $config->connection)
            && get_config('block_deft', 'enablebridge')
            && get_config('block_deft', 'enablevideo'),
            'taskid' => $this->task->get('id'),
            'title' => format_string($this->task->get_config()->name),
            'uniqueid' => uniqid(),
        ];
    }

    /**
     * Allow peer to join meeting
     *
     * @param stdClass $peer Peer record
     * @param string $username Jitsi id for user
     * @return array
     */
    public function join($peer, $username): array {
        global $DB;

        $peer->username = $username;

        $DB->update_record('block_deft_peer', $peer);

        $params = [
            'context' => \core\context\block::instance($this->task->get('instance')),
            'objectid' => $this->task->get('id'),
        ];

        $event = \block_deft\event\audiobridge_launched::create($params);
        $event->trigger();

        return [
            'status' => true,
            'id' => $peer->id,
        ];
    }

    /**
     * Publish peer sharing video
     *
     * @param bool $publish Whether to publish or unpublish
     * @return array
     */
    public function publish($publish): array {
        global $DB, $SESSION;

        $peer = $DB->get_record('block_deft_peer', ['id' => $SESSION->deft_session->peerid]);

        unset($peer->id);
        $peer->type = 'video';
        $peer->timecreated = time();
        $peer->timemodified = $peer->timecreated;

        $DB->set_field('block_deft_peer', 'status', 1, [
            'taskid' => $peer->taskid,
            'type' => 'video',
        ]);

        if ($publish) {
            $peer->id = $DB->insert_record('block_deft_peer', $peer);
        } else {
            $peer->username = '';
        }

        $params = [
            'context' => \core\context\block::instance($this->task->get('instance')),
            'objectid' => $this->task->get('id'),
        ];

        if ($publish) {
            $event = \block_deft\event\video_started::create($params);
        } else {
            $event = \block_deft\event\video_ended::create($params);
        }
        $event->trigger();

        return [
            'status' => true,
            'feed' => $peer->username,
        ];
    }
}
