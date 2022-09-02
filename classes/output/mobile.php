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
 * Mobile output class for Deft response block
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/deft/lib.php');
require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/lib/blocklib.php');

use block_deft\task;
use cache;
use block_deft\comment;
use stdClass;

/**
 * Mobile output class for Deft response block
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the video time course view for the mobile app.
     * @param array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     * @throws \required_capability_exception
     * @throws \coding_exception
     * @throws \require_login_exception
     * @throws \moodle_exception
     */
    public static function mobile_content_view($args) {
        global $OUTPUT, $DB, $CFG;

        if ($args['contextlevel'] == 'course') {
            $course = get_course($args['instanceid']);
            require_login($course);
        }

        $instance = block_instance_by_id($args['blockid']);

        if (
            ($option = $args['choice'] ?? null)
            && has_capability('block/deft:choose', $instance->context, $args['userid'])
            && !$DB->get_record_select(
                'block_deft_response',
                'task = :task AND userid = :userid AND timemodified > :timemodified',
                $args
            )
        ) {
            block_deft_output_fragment_choose([
                'context' => $instance->context,
                'id' => $args['task'],
                'option' => (int) ($option - 1),
            ]);
        }
        $data = (object) $instance->export_for_template($OUTPUT);
        $responses = $DB->get_records('block_deft_response', [
            'userid' => $args['userid'],
        ], '', 'task, response, timemodified');
        $choice = [];
        $cache = cache::make('block_deft', 'results');
        foreach ($data->tasks as $task) {
            $choice['choice' . $task->id] = 2;
            if ($task->type == 'choice') {
                if (task::get_record(['id' => $task->id])->get_state()->showsummary) {
                    $task->results = array_values($cache->get($task->id));
                }
                if (key_exists($task->id, $responses)) {
                    $config = task::get_record(['id' => $task->id])->get_config();
                    $selected = array_search($responses[$task->id]->response, $config->option);
                    $task->timemodified = $responses[$task->id]->timemodified;
                    if ($selected !== false) {
                        $choice['choice' . $task->id] = (string) ($selected + 1);
                    }
                } else {
                    $task->timemodified = 0;
                }
            }
        }
        $data->contextlevel = $args['contextlevel'];
        $data->instanceid = $args['instanceid'];

        $html = $OUTPUT->render_from_template('block_deft/mobile_view', $data);
        $js = 'var window=this;' . file_get_contents($CFG->dirroot . '/blocks/deft/amd/build/mobile.min.js');

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<div>'.$html.'</div>',
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'contextid' => $data->contextid,
                'token' => $data->token,
                'uniqid' => $data->uniqid,
            ] + $choice,
        ];
    }

    /**
     * Return the html for a single block instance
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     * @return string HTML
     */
    public static function mobile_comments_view($args): array {
        global $CFG, $OUTPUT;

        if ($args['contextlevel'] == 'course') {
            $course = get_course($args['instanceid']);
            require_login($course);
        }

        $task = task::get_record(['id' => $args['task']]);
        $config = $task->get_config();
        $instance = block_instance_by_id($task->get('instance'));
        $context = $instance->context;

        $course = get_course($context->get_course_context()->instanceid);
        $options = new stdClass();
        $options->context   = $context;
        $options->course    = $course;
        $options->area      = 'task';
        $options->itemid    = $task->get('id');
        $options->component = 'block_deft';
        $options->showcount  = true;
        $options->displaycancel = false;
        $comment = new comment($options);
        $comment->set_view_permission(true);
        $comment->set_fullwidth();

        $data = [
            'name' => $config->name,
            'label' => $config->label,
            'rawcomments' => $comment->get_comments(),
            'blockid' => $task->get('instance'),
            'task' => $task->get('id'),
        ];

        $js = 'var window=this;' . file_get_contents($CFG->dirroot . '/blocks/deft/amd/build/mobile.min.js');
        $instancedata = (object) $instance->export_for_template($OUTPUT);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('block_deft/mobile_comments', $data),
                ],
            ],
            'javascript' => $js,
            'otherdata' => [
                'contextid' => $instancedata->contextid,
                'token' => $instancedata->token,
                'uniqid' => $instancedata->uniqid,
            ],
        ];
    }
}
