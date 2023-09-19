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

        $html = $output->render_from_template('block_deft/mobile_view', $data);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<div>'.$html.'</div>',
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
        if ($peerid = $DB->get_field('block_deft_peer', 'id', [
            'userid' => $USER->id,
            'taskid' => $task->get('id'),
            'status' => 0,
            'sessionid' => null,
            'type' => 'venue',
            'uuid' => $args['uuid'],
        ])) {
            $DB->set_field('block_deft_peer', 'status', 1, [
                'userid' => $USER->id,
                'taskid' => $task->get('id'),
                'status' => 0,
                'sessionid' => null,
                'type' => 'venue',
                'uuid' => $args['uuid']
            ]);
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
console.log(this);
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            DeftVenue.roomid = $roomid;
            DeftVenue.peerid = $peerid;
            DeftVenue.remoteStreams = {};
            DeftVenue.currentFeed = 0;
            DeftVenue.taskid = " . $args['task'] . ";

            var ws = new WebSocket('wss://deftly.us/ws'),
                token = this.CONTENT_OTHERDATA.token;

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
                DeftVenue.currentFeed = 0;
                DeftVenue.remoteStream = null;
                DeftVenue.creatingSubscription = false;
            }
            setTimeout(() => {
                this.webrtcUp = false;
                this.Janus.init({
                    debug: 'none',
                    callback: () => {
                        DeftVenue.janus = new this.Janus({
                            server: '$server',
                            iceServers: {$iceservers},
                            success: () => {
                                // Attach audiobridge plugin.
                                DeftVenue.janus.attach({
                                    plugin: 'janus.plugin.audiobridge',
                                    opaqueId: 'audioroom-' + this.Janus.randomString(12),
                                    success: pluginHandle => {
                                        this.audioBridge = pluginHandle;
                                        DeftVenue.audioBridge = pluginHandle;
                                        DeftVenue.register(pluginHandle);
                                    },
                                    error: function(error) {
                                        alert(error);
                                    },
                                    onmessage: (msg, jsep) => {
                                        const event = msg.audiobridge;
                                        if (event) {
                                            if (event === 'joined') {
                                                // Successfully joined, negotiate WebRTC now
                                                if (msg.id) {
                                                    DeftVenue.updateParticipants();
                                                    if (!this.webrtcUp) {
                                                        this.webrtcUp = true;
                                                            const tracks = [];
                                                                tracks.push({
                                                                    type: 'audio',
                                                                    recv: true
                                                                });
                                                        navigator.mediaDevices.getUserMedia({
                                                            audio:true,
                                                            video:false
                                                        }).catch((e) => {
                                                            console.log(e);
                                                            document.querySelectorAll(
                                                                '[data-action=\"unmute\"]'
                                                            ).forEach(button => {
                                                                button.style.display = 'none';
                                                            });
                                                            return null;
                                                        }).then(audioStream => {
                                                            // Publish our stream.
                                                            const tracks = [];
                                                            if (audioStream) {
                                                                audioStream.getAudioTracks().forEach(track => {
                                                                    tracks.push({
                                                                        type: 'audio',
                                                                        capture: track,
                                                                        recv: true
                                                                    });
                                                                    DeftVenue.audioTrack = track;
                                                                    track.enabled = false;
                                                                    DeftVenue.monitorVolume(audioStream);
                                                                });
                                                            }
                                                            this.audioBridge.createOffer({
                                                                // We only want bidirectional audio
                                                                tracks: tracks,
                                                                customizeSdp: function(jsep) {
/*
                                                                    if (stereo && jsep.sdp.indexOf('stereo=1') == -1) {
                                                                        // Make sure that our offer contains stereo too
                                                                        jsep.sdp = jsep.sdp.replace(
                                                                            'useinbandfec=1', 'useinbandfec=1;stereo=1'
                                                                        );
                                                                    }
*/
                                                                },
                                                                success: (jsep) => {
                                                                    this.Janus.debug('Got SDP!', jsep);
                                                                    const publish = {request: 'configure', muted: false};
                                                                    this.audioBridge.send({message: publish, jsep: jsep});
                                                                },
                                                                error: function(error) {
                                                                    alert('WebRTC error... '+ error.message);
                                                                }
                                                            });

                                                            return audioStream;
                                                        }).catch(console.log);
                                                    }
                                                }
                                            } else if (event === 'destroyed') {
                                                // The room has been destroyed
                                                this.Janus.warn('The room has been destroyed!');
                                                alert('The room has been destroyed');
                                            } else if (event === 'event') {
                                                if (msg.participants) {
                                                    DeftVenue.updateParticipants(participants);
                                                } else if (msg.error) {
                                                    if (msg.error_code === 485) {
                                                        // This is a 'no such room' error: give a more meaningful description
                                                        alert(
                                                            '<p>Room <code>' + $roomid + '</code> is not configured.'
                                                        );
                                                    } else {
                                                        alert(msg.error_code + '-' + msg.error);
                                                    }
                                                    return;
                                                }
                                                if (msg.leaving) {
                                                    // One of the participants has gone away?
                                                    const leaving = msg.leaving;
                                                    this.Janus.log(
                                                        'Participant left: ' + leaving
                                                    );
                                                    document.querySelectorAll(
                                                        '#deft_audio [peerid=\"' + leaving + '\"]'
                                                    ).forEach(peer => {
                                                        peer.remove();
                                                    });
                                                }
                                            }
                                        }
                                        if (jsep) {
                                            this.Janus.debug('Handling SDP as well...', jsep);
                                            this.audioBridge.handleRemoteJsep({jsep: jsep});
                                        }
                                    },
                                    onremotetrack: (track, mid, on, metadata) => {
                                        this.Janus.debug(
                                            'Remote track (mid=' + mid + ') ' +
                                            (on ? 'added' : 'removed') +
                                            (metadata ? ' (' + metadata.reason + ') ' : '') + ':', track
                                        );
                                        if (this.remoteStream || track.kind !== 'audio') {
                                            return;
                                        }
                                        if (!on) {
                                            // Track removed, get rid of the stream and the rendering
                                            this.remoteStream = null;
                                            return;
                                        }
                                        this.remoteStream = new MediaStream([track]);
                                        this.Janus.attachMediaStream(document.getElementById('roomaudio'), this.remoteStream);
                                    },
                                    error: alert
                                });
                                DeftVenue.janus.attach( {
                                    plugin: 'janus.plugin.textroom',
                                    opaqueId: 'textroom-' + Janus.randomString(12),
                                    success: pluginHandle => {
                                        this.textroom = pluginHandle;
                                        DeftVenue.textroom = pluginHandle;
                                        Janus.log('Plugin attached! (' + this.textroom.getPlugin()
                                            + ', id=' + this.textroom.getId() + ')');
                                        // Setup the DataChannel
                                        const body = {request: 'setup'};
                                        Janus.debug('Sending message:', body);
                                        this.textroom.send({message: body});
                                    },
                                    error: function(error) {
                                        alert('  -- Error attaching plugin... ' + error);
                                        Janus.error('  -- Error attaching plugin...', error);
                                    },
                                    onmessage: (msg, jsep) => {
                                        Janus.debug(' ::: Got a message :::', msg);
                                        if (msg.error) {
                                            alert(msg.error_code + msg.error);
                                        }

                                        if (jsep) {
                                            // Answer
                                            this.textroom.createAnswer(
                                                {
                                                    jsep: jsep,
                                                    // We only use datachannels
                                                    tracks: [
                                                        {type: 'data'}
                                                    ],
                                                    success: (jsep) => {
                                                        Janus.debug('Got SDP!', jsep);
                                                        const body = {request: 'ack'};
                                                        this.textroom.send({message: body, jsep: jsep});
                                                    },
                                                    error: function(error) {
                                                        Janus.error('WebRTC error:', error);
                                                    }
                                                }
                                            );
                                        }
                                    },
                                    // eslint-disable-next-line no-unused-vars
                                    ondataopen: (label, protocol) => {
                                        const transaction = Janus.randomString(12),
                                            register = {
                                                textroom: 'join',
                                                transaction: transaction,
                                                room: $roomid,
                                                username: String($peerid),
                                                display: '',
                                            };
                                        this.textroom.data({
                                            text: JSON.stringify(register),
                                            error: function(reason) {
                                                alert('Error ' + reason);
                                            }
                                        });
                                    },
                                    ondata: (data) => {
                                        Janus.debug('We got data from the DataChannel!', data);
                                        const message = JSON.parse(data),
                                            event = message.textroom;

                                        if (event === 'message' && message.from != $peerid) {
                                            //this.handleMessage(message.from, {data: message.text});
                                            const data = JSON.parse(message.text);
                                            if (data.hasOwnProperty('raisehand')) {
                                                document.querySelectorAll(
                                                    '[data-peerid=\"' + message.from + '\"] [data-role=\"raisehand\"]'
                                                ).forEach(button => {
                                                    button.style.display = data.raisehand ? 'inline' : 'none';
                                                });
                                            };
                                            if (data.hasOwnProperty('volume')) {
                                                document.querySelectorAll(
                                                    '#participants ion-item[data-peerid=\"' + message.from + '\"]'
                                                ).forEach(indicator => {
                                                    indicator.querySelector('.indicator').style.opacity
                                                        = (data.volume.high + data.volume.mid + data.volume.high) / 3;
                                                    indicator.setAttribute('data-volume', data.volume.smooth);
                                                });
                                            }
                                        }
                                        if (event === 'error') {
                                            alert(error);
                                        }
                                        if (event === 'join') {
                                            DeftVenue.sendMessage(JSON.stringify({
                                                'raisehand': DeftVenue.raisehand
                                            }));
                                        }
                                    }
                                });
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

        $js = "var result = {DeftVenue: {
            raiseHand: function(state) {
                this.raisehand = state;
                document.querySelectorAll(
                    '[data-action=\"lowerhand\"], [data-action=\"raisehand\"]'
                ).forEach(function(button) {
                    if (state == (button.getAttribute('data-action') == 'raisehand')) {
                        button.style.display = 'none';
                    } else {
                        button.style.display = null;
                    }
                });
                this.sendMessage(JSON.stringify({
                    'raisehand': state
                }));
            },

            switchMute: function(state) {
                if (!this.audioTrack) {
                    return;
                }
                this.audioTrack.enabled = !state;
                document.querySelectorAll(
                    '[data-action=\"mute\"], [data-action=\"unmute\"]'
                ).forEach(function(button) {
                    if (state == (button.getAttribute('data-action') == 'unmute')) {
                        button.style.display = null;
                    } else {
                        button.style.display = 'none';
                    }
                });
            },

            sendMessage: function(text) {
                if (text && text !== '' && this.textroom) {
                    const message = {
                        textroom: 'message',
                        room: this.roomid,
                        transaction: Janus.randomString(12),
                        text: text
                    };
                    this.textroom.data({
                        text: JSON.stringify(message),
                        error: alert
                    });
                }
            },

            updateParticipants: function(list) {
                this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id).then(site => {

                    site.read('block_deft_get_participants', {
                        taskid: this.taskid
                    }, {getFromCache: false, saveToCache: false, reusePending: false}).then(response => {
                        response.participants.forEach(participant => {
                            if (participant.id == this.peerid) {
                               this.switchMute(participant.mute);
                            } else if (participant.status) {
                                document.querySelectorAll(
                                    '#participants [data-peerid=\"' + participant.id + '\"]'
                                ).forEach(item => {
                                    item.remove();
                                });
                            } else {
                                if (!document.querySelector('#participants [data-peerid=\"' + participant.id + '\"]')) {
                                    const item = document.createElement('ion-item');
                                    item.setAttribute('data-peerid', participant.id);
                                    item.innerHTML = participant.content;
                                    document.getElementById('participants').appendChild(item);
                                }
                            }
                            document.querySelectorAll(
                                '[data-peerid=\"' + participant.id + '\"] .indicator'
                            ).forEach(button => {
                                button.style.display = !participant.mute ? 'inline' : 'none';
                            });
                            document.querySelectorAll(
                                '[data-peerid=\"' + participant.id + '\"] [data-role=\"mute\"]'
                            ).forEach(button => {
                                button.style.display = participant.mute ? 'inline' : 'none';
                            });
                        });

                        this.subscribeTo(Number(response.feed));
                        return response;
                    }).catch(alert);
                    return site;
                });
            },

            /**
             * Process audio to provide visual feedback
             *
             * @param {MediaStream} audioStream Audio from user's microphone
             * @returns {MediaStream}
             */
            monitorVolume: function(audioStream) {
                if (audioStream) {
                    const audioContext = new AudioContext(),
                        source = audioContext.createMediaStreamSource(audioStream),
                        analyser = new AnalyserNode(audioContext, {
                            maxDecibels: -50,
                            minDecibels: -90,
                            fftSize: 2048,
                            smoothingTimeConstant: 0.3
                        }),
                        smoothanalyser = new AnalyserNode(audioContext, {
                            maxDecibels: -50,
                            minDecibels: -90,
                            fftSize: 2048,
                            smoothingTimeConstant: 0.6
                        }),
                        bufferLength = analyser.frequencyBinCount,
                        data = new Uint8Array(bufferLength),
                        smootheddata = new Uint8Array(bufferLength);
                    source.connect(analyser);
                    source.connect(smoothanalyser);
                    clearInterval(this.meterId);
                    this.meterId = setInterval(() => {
                        analyser.getByteFrequencyData(data);
                        smoothanalyser.getByteFrequencyData(smootheddata);
                        const volume = {
                            low: Math.min(1, data.slice(0, 16).reduce((a, b) => a + b, 0) / 2000),
                            mid: Math.min(1, data.slice(17, 31).reduce((a, b) => a + b, 0) / 1000),
                            high: Math.min(1, data.slice(32).reduce((a, b) => a + b, 0) / 4000),
                            smooth: Math.min(1, smootheddata.slice(0, 16).reduce((a, b) => a + b, 0) / 2000)
                                + Math.min(1, smootheddata.slice(17, 31).reduce((a, b) => a + b, 0) / 1000)
                                + Math.min(1, smootheddata.slice(32).reduce((a, b) => a + b, 0) / 4000)
                        },
                            message = JSON.stringify({volume: volume}),
                            peers = [];
                        document.querySelectorAll('.volume_indicator[data-peerid=\"' + this.peerid + '\"]').forEach(indicator => {
                            indicator.querySelectorAll('.low').forEach(low => {
                                low.style.opacity = volume.low;
                            });
                            indicator.querySelectorAll('.mid').forEach(mid => {
                                mid.style.opacity = volume.mid;
                            });
                            indicator.querySelectorAll('.high').forEach(high => {
                                high.style.opacity = volume.high;
                            });
                        });
                        this.sendMessage(message);
                        document.querySelectorAll('#participants > ion-item').forEach(peer => {
                            peers.push(peer);
                        });
                        peers.sort((a, b) => {
                            let volume = 0;
                            volume += -Number(a.getAttribute('data-volume'));
                            volume += Number(b.getAttribute('data-volume'));
                            return volume;
                        });
                        peers.forEach(peer => {
                            document.querySelector('#participants').appendChild(peer);
                        });
                    }, 500);
                }

                return audioStream;
            },

            register: function(pluginHandle) {
                const args = {
                    handle: pluginHandle.getId(),
                    id: Number(this.peerid),
                    plugin: pluginHandle.plugin,
                    room: this.roomid,
                    session: pluginHandle.session.getSessionId()
                };

                if (pluginHandle.plugin === 'janus.plugin.videoroom') {
                    args.ptype = false;
                    args.feed = this.currentFeed;
                }

                this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id).then(site => {
                    site.read('block_deft_join_room', args, {getFromCache: false, saveToCache: false, reusePending: false});
                });
            },

            subscribeTo: function(source) {
                if (!this.janus || !this.audioBridge || this.creatingSubscription || source === this.currentFeed) {
                    return;
                }

                if (this.remoteStream) {
                    this.videoroom.detach();
                    this.videoroom = null;
                    this.remoteStreams = {};
                    this.remoteStream = null;
                    this.creatingSubscription = false;
                }

                if (!source) {
                    document.getElementById('roomvideo').style.display = 'none';
                    return;
                }

                this.creatingSubscription = true;

                this.currentFeed = source;

                this.janus.attach(
                    {
                        plugin: 'janus.plugin.videoroom',
                        opaqueId: 'videoroom-' + Janus.randomString(12),
                        success: pluginHandle => {
                            this.videoroom = pluginHandle;
                            this.register(pluginHandle);
                        },
                        error: alert,
                        onmessage: this.onMessage.bind(this),
                        onremotetrack: (track, mid, on, metadata) => {
                            Janus.debug(
                                'Remote track (mid=' + mid + ') ' +
                                (on ? 'added' : 'removed') +
                                (metadata ? ' (' + metadata.reason + ') ' : '') + ':', track
                            );
                            if (!on) {
                                // Track removed, get rid of the stream and the rendering
                                delete this.remoteStreams[mid];
                                return;
                            }
                            if (!this.remoteStreams.hasOwnProperty(mid) && track.kind === 'video') {
                                this.remoteStreams[mid] = track;
                                if (this.remoteStream) {
                                    return;
                                }
                                this.remoteStream = new MediaStream([track]);
                                this.remoteStream.mid = mid;
                                this.attachVideo(this.remoteStream);
                            }
                        }
                    }
                );
            },

            observe: function(records, observer) {
                if (!this.janus || document.querySelector('audio')) {
                    return;
                }

                this.janus.destroy();

                this.janus = null;

                if (this.ws) {
                    this.ws.close();
                }

                if (this.audioTrack) {
                    this.audioTrack.stop();
                }
                this.audioTrack = null;
                clearInterval(this.meterId);

                this.remoteStream = null;
                this.remoteStreams = {};
                this.currentFeed = 0;
                this.creatingSubscription = false;

                this.raisehand = null;

                observer.disconnect();

                this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id).then(site => {
                    site.read('block_deft_venue_settings', {
                        mute: true,
                        peerid: this.peerid,
                        status: true,
                        uuid: this.Device.uuid
                    }).catch(alert);

                    return site;
                });
            },

            onMessage: function(msg, jsep) {
                const event = msg.videoroom,
                    pluginHandle = this.videoroom;
                Janus.debug(' ::: Got a message :::', msg);
                Janus.debug('Event: ' + event);
                switch (event) {
                    case 'destroyed':
                        // The room has been destroyed
                        Janus.warn('The room has been destroyed!');
                        Notification.alert('', 'The room has been destroyed', function() {
                            window.close();
                        });
                        break;
                    case 'attached':
                        this.creatingSubscription = false;
                        this.updateParticipants();
                        break;
                    case 'event':
                        if (msg.error) {
                            if (msg.error_code === 485) {
                                // This is a 'no such room' error: give a more meaningful description
                                alert(
                                    '<p>Apparently room <code>' + this.roomid + '</code> is not configured</p>'
                                );
                            } else {
                                alert(msg.error_code + ' ' + msg.error);
                            }
                            return;
                        }
                        break;
                }
                if (jsep) {
                    Janus.debug('Handling SDP as well...', jsep);
                    // Answer and attach
                    pluginHandle.createAnswer(
                        {
                            jsep: jsep,
                            tracks: [
                                {type: 'data'}
                            ],
                            success: function(jsep) {
                                Janus.debug('Got SDP!');
                                Janus.debug(jsep);
                                let body = {request: 'start', room: this.roomid};
                                pluginHandle.send({message: body, jsep: jsep});
                            },
                            error: function(error) {
                                Janus.error('WebRTC error:', error);
                                alert('WebRTC error... ' + error.message);
                            }
                        }
                    );
                }
            },

            attachVideo: function(videoStream) {
                document.getElementById('roomvideo').style.display = 'block';
                Janus.attachMediaStream(
                    document.getElementById('roomvideo'),
                    videoStream
                );
            },

            CoreSitesProvider: this.CoreSitesProvider,

            Device: this.Device
        }};

        " . file_get_contents("$CFG->dirroot/blocks/deft/lib/adapter.js") . "
        " . file_get_contents("$CFG->dirroot/blocks/deft/lib/janus.js") . "

        result.Janus = Janus;
        result;";

        return [
            'javascript' => $js,
        ];
    }
}
