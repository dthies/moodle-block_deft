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

use cache;
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
class choice extends text implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     */
    public function __construct($context, $task) {
        $this->task = $task;
        $this->context = $context;
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
        global $USER;

        if (empty($this->state->visible)) {
            return '';
        }

        if (!empty($this->state->showsummary)) {
            $summary = new summary($this->context, $this->task);
        }
        $cache = cache::make('block_deft', 'results');
        $response = $cache->get($this->task->id . 'x' . $USER->id);
        $options = [[
            'key' => '',
            'value' => '',
        ]];
        foreach (array_filter($this->config->option) as $key => $option) {
            $options[] = [
                'key' => $key,
                'value' => $option,
                'selected' => !empty($response) && $response->response === $option,
            ];
        }

        return [
            'contextid' => $this->context->id,
            'disabled' => !empty($this->state->preventresponse),
            'id' => $this->task->id,
            'key' => array_search($response->response ?? null, array_filter($this->config->option)),
            'lastmodified' => max($response->timemodified ?? 0, empty($summary) ? 0 : $summary->last_modified()),
            'name' => !empty($this->state->showtitle) ? $this->config->name : '',
            'question' => format_text($this->config->question, FORMAT_MOODLE, [
                'blanktarget' => true,
                'para' => true,
            ]),
            'options' => $options,
            'results' => !empty($summary) ? array_values($summary->export_for_template($output)['results']) : null,
            'summary' => !empty($summary) ? $output->render($summary) : null,
            'visible' => true,
        ];
    }
}
