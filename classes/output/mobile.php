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
use block_deft\comment;
use block_deft\event\venue_started;
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
        global $CFG, $DB, $PAGE;

        if ($args['contextlevel'] == 'course') {
            $course = get_course($args['instanceid']);
            require_login($course);
        }

        $instance = block_instance_by_id($args['blockid']);

        if (
            key_exists('choice', $args)
            && has_capability('block/deft:choose', $instance->context, $args['userid'])
            && (
                !$DB->get_record_select(
                    'block_deft_response',
                    'task = :task AND userid = :userid AND timemodified > :timemodified',
                    $args
                )
                || (
                    ($args['choice'] === '')
                    && $DB->get_record_select(
                        'block_deft_response',
                        'task = :task AND userid = :userid AND timemodified = :timemodified',
                        $args
                    )
                )
            )
        ) {
            block_deft_output_fragment_choose([
                'context' => $instance->context,
                'id' => $args['task'],
                'option' => $args['choice'],
            ]);
        }
        $output = $PAGE->get_renderer('block_deft');
        $data = (object) $instance->export_for_template($output);
        $choice = [];
        foreach ($data->tasks as $task) {
            if (!empty($task->choice)) {
                $choice['choice' . $task->id] = (string) $task->choice['key'];
            }
        }
        $data->contextlevel = $args['contextlevel'];
        $data->instanceid = $args['instanceid'];
        $data->title = format_string(
            $instance->config->title ?: $instance->title,
            ['context' => $instance->context]
        );

        $html = $output->render_from_template('block_deft/mobile_view', $data);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<div>' . $html . '</div>',
                ],
            ],
            'javascript' => self::template_js(),
            'otherdata' => [
                'contextid' => $data->contextid,
                'token' => $data->token,
                'uniqid' => $data->uniqid,
            ] + $choice,
        ];
    }

    /**
     * Return the js for template
     *
     * @return string Javascript
     */
    public static function template_js(): string {
        if (get_config('block_deft', 'enableupdating')) {
            return "
                var ws = new WebSocket('wss://deftly.us/ws'),
                    token = this.CONTENT_OTHERDATA.token;

                ws.onopen = function() {
                    ws.send(token);
                };

                ws.onclose = () => {
                    var id = setInterval(() => {
                        if (navigator.onLine) {
                            clearInterval(id);
                            this.refreshContent(false);
                        }
                    }, 5000);
                };

                ws.addEventListener('message', () => {
                    setTimeout(function() {
                        if (navigator.onLine && !document.querySelector('textarea:focus')) {
                            this.refreshContent(false);
                        }
                    }.bind(this));
                });";
        } else {
            return '';
        }
    }

    /**
     * Return the html for a single block instance
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     * @return string HTML
     */
    public static function mobile_comments_view($args): array {
        global $CFG, $PAGE;

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
        $options->context = $context;
        $options->course = $course;
        $options->area = 'task';
        $options->itemid = $task->get('id');
        $options->component = 'block_deft';
        $options->showcount = true;
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

        $output = $PAGE->get_renderer('block_deft');
        $instancedata = (object) $instance->export_for_template($output);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $output->render_from_template('block_deft/mobile_comments', $data),
                ],
            ],
            'javascript' => self::template_js(),
            'otherdata' => [
                'contextid' => $instancedata->contextid,
                'token' => $instancedata->token,
                'uniqid' => $instancedata->uniqid,
            ],
        ];
    }

    /**
     * Return the html for view a venue
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     * @return string HTML
     */
    public static function mobile_venue_view($args): array {
        global $CFG, $DB, $PAGE, $USER;

        if ($args['contextlevel'] == 'course') {
            $course = get_course($args['instanceid']);
            require_login($course);
        }

        $task = task::get_record(['id' => $args['task']]);
        $config = $task->get_config();
        $instance = block_instance_by_id($task->get('instance'));
        $context = $instance->context;

        $course = get_course($context->get_course_context()->instanceid);

        $data = [
            'blockid' => $task->get('instance'),
            'intro' => format_text(
                file_rewrite_pluginfile_urls(
                    $config->intro->text ?? '',
                    'pluginfile.php',
                    $context->id,
                    'block_deft',
                    'venue',
                    $task->get('id')
                ),
                $config->intro->format ?? FORMAT_MOODLE,
                ['context' => $context]
            ),
            'mute' => true,
            'name' => $config->name,
            'task' => $task->get('id'),
        ];

        $output = $PAGE->get_renderer('block_deft');
        $instancedata = (object) $instance->export_for_template($output);

        $room = new \block_deft\janus_room($task);
        $server = $room->get_server();
        $roomid = $room->get_roomid();
        $socket = new \block_deft\socket($context);
        $iceservers = json_encode($socket->ice_servers());
        $timecreated = time();
        if (
            $peerid = $DB->get_field('block_deft_peer', 'id', [
            'userid' => $USER->id,
            'taskid' => $task->get('id'),
            'status' => 0,
            'sessionid' => null,
            'type' => 'venue',
            'uuid' => $args['uuid'],
            ])
        ) {
            $DB->set_field('block_deft_peer', 'status', 1, [
                'userid' => $USER->id,
                'taskid' => $task->get('id'),
                'status' => 0,
                'sessionid' => null,
                'type' => 'venue',
                'uuid' => $args['uuid'],
            ]);
        } else {
            $params = [
                'context' => $context,
                'objectid' => $taskid,
            ];

            $event = venue_started::create($params);
            $event->trigger();
        }
        $peerid = $DB->insert_record('block_deft_peer', [
            'userid' => $USER->id,
            'mute' => true,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
            'taskid' => $task->get('id'),
            'type' => 'venue',
            'uuid' => $args['uuid'],
        ]);

        $socket->dispatch();

        $js = "
            var DeftVenue = this.DeftVenue,
                Janus = this.Janus,
                observer = new MutationObserver(DeftVenue.observe.bind(DeftVenue));
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            DeftVenue.addListeners();

            DeftVenue.roomid = $roomid;
            DeftVenue.peerid = $peerid;
            DeftVenue.remoteStreams = {};
            DeftVenue.currentFeed = 0;
            DeftVenue.taskid = " . $args['task'] . ";

            var ws = new WebSocket('wss://deftly.us/ws'),
                token = '$instancedata->token';

            DeftVenue.ws = ws;
            ws.onopen = function() {
                ws.send(token);
            };

            ws.onclose = () => {
                if (!document.querySelector('audio#roomaudio')) {
                    return;
                }
                var id = setInterval(() => {
                    if (navigator.onLine) {
                        this.refreshContent(false);
                        clearInterval(id);
                    }
                }, 1000);
            };

            ws.addEventListener('message', () => {
                if (!document.querySelector('audio#roomaudio')) {
                    ws.close;
                    return;
                }
                setTimeout(() => {
                    if (navigator.onLine) {
                        DeftVenue.updateParticipants();
                    }
                });
            });

            if (DeftVenue.janus) {
                DeftVenue.janus.destroy();
                DeftVenue.janus = null;
                DeftVenue.audioBridge = null;
                DeftVenue.textroom = null;
                DeftVenue.currentFeed = 0;
                DeftVenue.remoteStream = null;
                DeftVenue.creatingSubscription = false;
            }
            setTimeout(() => {
                DeftVenue.webrtcUp = false;
                this.Janus.init({
                    debug: 'none',
                    callback: () => {
                        DeftVenue.janus = new this.Janus({
                            server: '$server',
                            iceServers: {$iceservers},
                            success: () => {
                                // Attach audiobridge plugin.
                                DeftVenue.attachAudioBridge();
                                DeftVenue.attachTextRoom();
                            },
                            error: alert
                        });
                    },
                    error: alert
                });
            });";

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $output->render_from_template('block_deft/mobile_venue', $data),
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

    /**
     * Add venue js library
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     */
    public static function init($args): array {
        global $CFG;

        $js = "var result = {};

        " . file_get_contents("$CFG->dirroot/blocks/deft/mobile/venue.js") . "
        " . file_get_contents("$CFG->dirroot/blocks/deft/mobile/adapter.js") . "
        " . file_get_contents("$CFG->dirroot/blocks/deft/mobile/janus.js") . "

        result = {
            DeftVenue: DeftVenue,
            Janus: Janus
        };

        result;";

        return [
            'javascript' => $js,
        ];
    }
}
