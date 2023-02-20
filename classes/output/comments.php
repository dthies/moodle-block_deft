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

use block_deft\comment;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for deft choice block.
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comments implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     * @param array $options Optional display data
     */
    public function __construct($context, $task, $options = null) {
        $this->context = $context;
        $this->task = $task;
        $this->config = json_decode($task->configdata);
        $this->state = json_decode($task->statedata);
        $course = get_course($context->get_course_context()->instanceid);
        $args = new stdClass();
        $args->context = $context;
        $args->course = $course;
        $args->area = 'task';
        $args->itemid = $task->id;
        $args->component = 'block_deft';
        $args->linktext = empty($this->config->label) ? get_string('comments') : $this->config->label;
        $args->notoggle = true;
        $args->showcount = true;
        $args->autostart = !empty($this->state->expandcomments) || !empty($task->opencomments);
        $args->displaycancel = false;
        $this->comment = new comment($args);
        $this->comment->set_view_permission(true);
        $this->comment->set_fullwidth();
        $this->options = $options;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        if (empty($this->state->visible)) {
            return '';
        }

        return [
            'lastmodified' => !empty($this->comment) ? $this->comment->last_modified() : $this->task->timemodified,
            'name' => !empty($this->state->showtitle) ? $this->config->name : '',
            'task' => $this->task->id,
            'count' => $this->comment->count(),
            'label' => empty($this->config->label) ? get_string('comments') : $this->config->label,
            'collapsible' => empty($this->state->expandcomments),
            'rawcomments' => !empty($this->comment) ? $this->comment->get_comments() : null,
            'expandcomments' => !empty($this->state->expandcomments)
                || in_array($this->task->id, $this->options->opencomments ?? []),
            'visible' => !empty($this->state->visible),
        ];
    }
}
