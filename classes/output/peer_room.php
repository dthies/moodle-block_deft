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
 * Peer room handler
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_room implements renderable, templatable {
    /** @var $context The context of the block */
    protected $context = null;

    /** @var $socket Socket object */
    protected $socket = null;

    /** @var $task Task configuration */
    protected $task = null;

    /** @var $record Room database record */
    protected $record = null;

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
     * @var ID for room
     */
    protected int $roomid = 0;

    /**
     * Constructor
     *
     * @param task $task Task associated with venue
     */
    public function __construct(task $task) {
        global $DB, $USER;

        $this->task = $task;
        $this->itemid = $task->get('id');
        $this->context = \core\context\block::instance($task->get('instance'));
        $this->socket = new socket($this->context);
    }

    /**
     * Get room id
     *
     * @return int
     */
    public function get_roomid(): int {
        return $this->socket->get_room();
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $SESSION;

        $config = $this->task->get_config();

        return [
            'autogaincontrol' => !empty(get_config('block_deft', 'autogaincontrol')),
            'canmanage' => has_capability('block/deft:moderate', $this->context),
            'contextid' => $this->context->id,
            'echocancellation' => !empty(get_config('block_deft', 'echocancellation')),
            'enablevideo' => false,
            'iceservers' => json_encode($this->socket->ice_servers()),
            'noisesuppression' => !empty(get_config('block_deft', 'noisesuppression')),
            'throttle' => get_config('block_deft', 'throttle'),
            'peerid' => $SESSION->deft_session->peerid,
            'peers' => '[]',
            'peerconnection' => ($config->connection ?? 'peer') == 'peer',
            'samplerate' => get_config('block_deft', 'samplerate'),
            'room' => $this->socket->get_room(),
            'server' => $this->socket->get_server(),
            'sharevideo' => false,
            'taskid' => $this->task->get('id'),
            'token' => $this->socket->get_token(),
            'title' => format_string($this->task->get_config()->name),
            'uniqueid' => uniqid(),
        ];
    }
}
