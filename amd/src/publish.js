/**
 * Manage venue connections
 *
 * @module     block_deft/venue_manager
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import adapter from "core/adapter";
import Ajax from "core/ajax";
import Janus from 'block_deft/janus-gateway';
import Log from "core/log";
import Notification from "core/notification";

export default class Publish {

    /**
     * Listen for comment actions
     *
     * @param {int} contextid Context id of block
     * @param {array} iceServers ICE server array to configure peers
     * @param {int} roomid
     * @param {string} server
     * @param {int} peerid
     */
    constructor(
        contextid, iceServers, roomid, server, peerid
    ) {
        this.contextid = contextid;
        this.iceServers = iceServers;
        this.roomid = roomid;
        this.server = server;
        this.peerid = peerid;

        window.adapter = adapter;

        this.ptype = 'publish';

        document.querySelector('body').removeEventListener('venueclosed', this.handleClose.bind(this));
        document.querySelector('body').addEventListener('venueclosed', this.handleClose.bind(this));
    }

    /**
     * Start to establish the peer connections
     */
    startConnection() {

        // Initialize the library (all console debuggers enabled)
        Janus.init({
            debug: "all", callback: () => {
                // Create session.
                this.janus = new Janus(
                    {
                        server: this.server,
                        iceServers: this.iceServers,
                        success: () => {
                            // Attach to video room test plugin
                            this.attach();
                        },
                        error: (error) => {
                            this.restart = true;
                            Log.debug(error);
                        }
                    }
                );
                document.querySelector('body').addEventListener('venueclosed', this.janus.destroy);
            }
        });
    }

    /**
     * Register the room
     *
     * @param {object} pluginHandle
     * @return {Promise}
     */
    register(pluginHandle) {
        // Try a registration
        return Ajax.call([{
            args: {
                handle: pluginHandle.getId(),
                id: Number(this.peerid),
                plugin: pluginHandle.plugin,
                room: this.roomid,
                ptype: this.ptype == 'publish',
                session: pluginHandle.session.getSessionId()
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_join_room'
        }])[0].then(response => {
            if (pluginHandle.plugin == 'janus.plugin.videoroom') {
                this.feed = response.id;
            }

            return response;
        }).catch(Notification.exception);
    }

    onLocalTrack(track, on) {
        Log.debug(on);
        return;
    }

    onRemoteTrack() {
        return;
    }

    onAttached(publishers) {
        Log.debug(publishers);
    }

    /**
     * Handle Janus plugin message
     *
     * @param {String} msg message
     * @param {String} jsep negotiation
     */
    onMessage(msg, jsep) {
        Log.debug(msg);
        Janus.debug(" ::: Got a message :::", msg);
        const event = msg.videoroom;
        Janus.debug("Event: " + event);
        switch (event) {
            case 'joined':
                // Successfully joined, negotiate WebRTC now
                if (msg.id) {
                    Log.debug("Successfully joined room " + msg.room + " with ID " + this.feed);
                    if (!this.webrtcUp) {
                        this.webrtcUp = true;
                        this.processStream([{type: 'data'}]);
                    }
                }
                break;
            case 'destroyed':
                // The room has been destroyed
                Janus.warn("The room has been destroyed!");
                Notification.alert('', "The room has been destroyed");
                break;
            case 'event':
                if (msg.configured) {
                    this.videoroom.webrtcStuff.pc.removeEventListener(
                        'iceconnectionstatechange',
                        this.publishFeed.bind(this)
                    );
                    this.videoroom.webrtcStuff.pc.addEventListener(
                        'iceconnectionstatechange',
                        this.publishFeed.bind(this)
                    );
                    setTimeout(this.publishFeed.bind(this));
                } else if (msg.error) {
                    if (msg.error_code === 485) {
                        // This is a "no such room" error: give a more meaningful description
                        Notification.alert(
                            "<p>Apparently room <code>" + this.roomid + "</code> (the one this demo uses as a test room) " +
                            "does not exist...</p><p>Do you have an updated <code>janus.plugin.audiobridge.jcfg</code> " +
                            "configuration file? If not, make sure you copy the details of room <code>" + this.roomid + "</code> " +
                            "from that sample in your current configuration file, then restart Janus and try again."
                        );
                    } else if (msg.error_code === 435) {
                        Log.debug(msg.error);
                    } else {
                        Notification.alert(msg.error_code, msg.error);
                    }
                    return;
                } else {
                    Log.debug(Object.keys(msg));
                }
                break;
        }
        if (jsep) {
            Janus.debug("Handling SDP as well...", jsep);
            this.videoroom.handleRemoteJsep({jsep: jsep});
        }
    }

    /**
     * Handle click of button
     *
     * @param {Event} e
     * @returns {Bool}
     */
    handleClick(e) {
        const button = e.target.closest(
            '[data-roomid="' + this.roomid + '"] [data-action="publish"],  [data-roomid="'
                + this.roomid + '"] [data-action="unpublish"]'
        );
        if (button) {
            const action = button.getAttribute('data-action'),
                type = button.getAttribute('data-type');
            e.stopPropagation();
            e.preventDefault();
            document.querySelectorAll(
                '[data-region="deft-venue"] [data-action="publish"],  [data-region="deft-venue"] [data-action="unpublish"]'
            ).forEach(button => {
                if ((button.getAttribute('data-action') == action) && (button.getAttribute('data-type') == type)) {
                    button.classList.add('hidden');
                } else {
                    button.classList.remove('hidden');
                }
            });
            switch (action) {
                case 'publish':
                    Log.debug(type);
                    if (type == 'display') {
                        this.shareDisplay();
                    } else {
                        this.shareCamera();
                    }

                    this.processStream([]);
                    break;
                case 'unpublish':
                    if (this.videoInput) {
                        this.videoInput.then(videoStream => {
                            if (videoStream) {
                                videoStream.getTracks().forEach(track => {
                                    track.stop();
                                });
                            }
                            this.videoInput = null;

                            return videoStream;
                        }).catch(Notification.exception);
                    }
                    this.unpublish();
            }
        }

        return true;
    }

    /**
     * Handle close of windoww
     */
    handleClose() {
        if (this.videoInput) {
            this.videoInput.then(videoStream => {
                if (videoStream) {
                    videoStream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                return videoStream;
            }).catch(Notification.exception);
        }

        this.janus.destroy();

        document.querySelectorAll(
            '[data-region="deft-venue"] [data-action="publish"]'
        ).forEach(button => {
            button.classList.remove('hidden');
        });
        document.querySelectorAll(
            '[data-region="deft-venue"] [data-action="unpublish"]'
        ).forEach(button => {
            button.classList.add('hidden');
        });
    }

    /**
     * Set video source to user camera
     */
    shareCamera() {
        const videoInput = this.videoInput;

        this.videoInput = navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        }).then(videoStream => {
            if (videoInput) {
                videoInput.then(videoStream => {
                    if (videoStream) {
                        videoStream.getTracks().forEach(track => {
                            track.stop();
                        });
                    }
                    return videoStream;
                }).catch(Notification.exception);
            }

            return videoStream;
        }).catch((e) => {
            Log.debug(e);

            return videoInput;
        });
    }

    /**
     * Set video source to display surface
     */
    shareDisplay() {
        const videoInput = this.videoInput || Promise.resolve(null);

        this.videoInput = navigator.mediaDevices.getDisplayMedia({
            video: true,
            audio: true,
        }).then(videoStream => {
            videoInput.then(videoStream => {
                if (videoStream) {
                    videoStream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                return videoStream;
            }).catch(Notification.exception);

            videoStream.type = 'display';

            return videoStream;
        }).catch((e) => {
            Log.debug(e);

            videoInput.then(videoStream => {
                document.querySelectorAll(
                    '[data-region="deft-venue"] [data-action="publish"],  [data-region="deft-venue"] [data-action="unpublish"]'
                ).forEach(button => {
                    if (videoStream) {
                        if (
                            (button.getAttribute('data-action') == 'unpublish')
                            || (button.getAttribute('data-type') === 'display')
                        ) {
                            button.classList.remove('hidden');
                        } else {
                            button.classList.add('hidden');
                        }
                    } else {
                        if (button.getAttribute('data-action') == 'unpublish') {
                            button.classList.add('hidden');
                        } else {
                            button.classList.remove('hidden');
                        }
                    }
                });

                return videoStream;
            }).catch(Notification.exception);

            return videoInput;
        });
    }

    /**
     * Publish current video feed
     *
     * @returns {Promise}
     */
    publishFeed() {
        if (
            this.videoroom.webrtcStuff.pc
            && this.videoroom.webrtcStuff.pc.iceConnectionState == 'connected'
        ) {
                return Ajax.call([{
                    args: {
                        id: Number(this.peerid),
                        room: this.roomid,
                    },
                    contextid: this.contextid,
                    fail: Notification.exception,
                    methodname: 'block_deft_publish_feed'
                }])[0].then((result) => {
                    setTimeout(() => {
                        this.videoroom.webrtcStuff.pc.getTransceivers().forEach(transceiver => {
                            const sender = transceiver.sender;
                            if (
                                sender.track
                                && this.selectedTrack
                                && (sender.track.id == this.selectedTrack.id)
                            ) {
                                const message = JSON.stringify({
                                    feed: this.feed,
                                    mid: transceiver.mid
                                });
                                this.videoroom.data({
                                    text: message,
                                    error: Log.debug
                                });
                            }
                        });
                    });

                    return result;
                }).catch(Notification.exception);
        }

        return Promise.resolve(null);
    }

    /**
     * Stop video feed
     *
     * @param {string} kind Track type
     * @returns {RTCTransceivr}
     */
    getTransceiver(kind) {
        let result = null;

        if (
            this.videoroom.webrtcStuff.pc
            && this.videoroom.webrtcStuff.pc.iceConnectionState == 'connected'
        ) {
            this.videoroom.webrtcStuff.pc.getTransceivers().forEach(transceiver => {
                const sender = transceiver.sender;
                if (
                    sender.track
                    && sender.track.id
                    && (sender.track.kind == (kind || 'video'))
                ) {
                    result = transceiver;
                }
            });
        }

        return result;
    }

    /**
     * Stop video feed
     *
     * @returns {Promise}
     */
    unpublish() {
        document.querySelectorAll(
            '[data-region="deft-venue"] [data-action="publish"]'
        ).forEach(button => {
            button.classList.remove('hidden');
        });
        document.querySelectorAll(
            '[data-region="deft-venue"] [data-action="unpublish"]'
        ).forEach(button => {
            button.classList.add('hidden');
        });
        return Ajax.call([{
            args: {
                id: Number(this.feed),
                publish: false,
                room: this.roomid
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_publish_feed'
        }])[0];
    }

    /**
     * Attach video plugin
     */
    attach() {
        this.janus.attach(
            {
                plugin: "janus.plugin.videoroom",
                opaqueId: "videoroom-" + Janus.randomString(12),
                success: pluginHandle => {
                    this.videoroom = pluginHandle;
                    this.register(pluginHandle);
                },
                error: function(error) {
                    Janus.error("  -- Error attaching plugin...", error);
                    Notification.alert('', "Error attaching plugin... " + error);
                },
                ondata: (data) => {
                    const message = JSON.parse(data);
                    if (message && message.feed) {
                        const publish = {
                            request: 'update',
                            subscribe: [{
                                feed: message.feed,
                                mid: message.mid,
                            }]
                        };
                        this.videoroom.send({
                            message: publish
                        });
                    }
                },
                onlocaltrack: this.onLocalTrack.bind(this),
                onremotetrack: this.onRemoteTrack.bind(this),
                onmessage: this.onMessage.bind(this)
            }
        );
    }

    /**
     * Find track changes and begin negotiation
     *
     * @param {array} tracks Additonal tracks
     */
    processStream(tracks) {
        this.videoInput.then(videoStream => {
            if (videoStream && (this.currentStream !== videoStream)) {
                const transceiver = this.getTransceiver();
                videoStream.getVideoTracks().forEach(track => {
                    track.addEventListener('ended', () => {
                        if (this.selectedTrack.id == track.id) {
                            this.unpublish();
                        }
                    });
                    if (transceiver) {
                        this.videoroom.replaceTracks({
                            tracks: [{
                                type: 'video',
                                mid: transceiver.mid,
                                capture: track
                            }],
                            error: Notification.exception
                        });

                        this.selectedTrack = track;
                        return;
                    }
                    tracks.push({
                        type: 'video',
                        capture: track,
                        recv: false
                    });
                    this.selectedTrack = track;
                });
                if (!tracks.length) {
                    return videoStream;
                }
                this.videoroom.createOffer({
                    tracks: tracks,
                    success: (jsep) => {
                        Janus.debug("Got SDP!", jsep);
                        const publish = {
                            request: "configure",
                            video: true,
                            audio: false
                        };
                        this.videoroom.send({
                            message: publish,
                            jsep: jsep
                        });
                    },
                    error: function(error) {
                        Janus.error("WebRTC error:", error);
                        Notification.alert("WebRTC error... ", error.message);
                    }
                });
            }

            return videoStream;
        }).catch(Notification.exception);
    }
}
