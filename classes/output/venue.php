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
 * Class containing data for deft choice block.
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use core_course\external\course_summary_exporter;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class containing data for deft choice block.
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class venue implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     */
    public function __construct($context, $task) {
        $this->context = $context;
        $this->task = $task;
        $this->config = json_decode($task->configdata);
        $this->state = json_decode($task->statedata);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $SESSION;

        if (empty($this->state->visible)) {
            return [];
        }

        if (!empty($SESSION->deft_session)) {
            $settings = $DB->get_record('block_deft_peer', [
                'id' => $SESSION->deft_session->peerid,
                'taskid' => $this->task->id ,
            ]);
        }

        $lastmodified = $DB->get_field_sql('
            SELECT MAX(p.timemodified)
              FROM {block_deft_peer} p
             WHERE p.taskid = ?',
            [$this->task->id]
        );
        if (has_capability('block/deft:joinvenue', $this->context)) {
            $peers = $DB->get_records_sql('
                SELECT p.id AS peerid, p.mute, u.*
                  FROM {block_deft_peer} p
                  JOIN {sessions} s ON p.sessionid = s.id
                  JOIN {user} u ON p.userid = u.id
                 WHERE p.status = 0
                       AND p.taskid = ?',
                [$this->task->id]
            );
            foreach ($peers as $peer) {
                $peer->fullname = fullname($peer);
            }
        } else {
            $peers = [];
        }
        $url = new moodle_url('/blocks/deft/venue.php', ['task' => $this->task->id]);
        return [
            'active' => !empty($settings) && !$settings->status,
            'canjoin' => has_capability('block/deft:joinvenue', $this->context),
            'count' => count($peers),
            'contextid' => $this->context->id,
            'peers' => array_values($peers),
            'lastmodified' => max($lastmodified, $this->task->timemodified, $settings->timemodified ?? 0),
            'limit' => $this->config->limit ?? 0,
            'mute' => !empty($settings->mute),
            'name' => !empty($this->state->showtitle) ? $this->config->name : '',
            'content' => format_text($this->config->content, FORMAT_MOODLE, [
                'blanktarget' => true,
                'para' => true,
            ]),
            'peerid' => $settings->peerid ?? 0,
            'popup' => !isset($this->config->windowoption) || $this->config->windowoption != 'openinwindow',
            'url' => $url->out(),
        ];
    }
}
