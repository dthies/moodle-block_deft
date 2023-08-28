/**
 * Manage venue connections
 *
 * @module     block_deft/venue_manager
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Janus from 'block_deft/janus-gateway';
import Publish from 'block_deft/publish';
import Log from "core/log";
import Notification from "core/notification";

export default class Subscribe extends Publish {

    /**
     * Start to establish the peer connections
     *
     * @param {int} feed Initial feed
     */
    startConnection(feed) {
        this.feed = feed;
        this.current = feed;
        this.transactions = {};

        this.creatingSubscription = true;

        this.remoteStreams = {};

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
                            this.janus.attach(
                                {
                                    plugin: "janus.plugin.videoroom",
                                    opaqueId: "videoroom-" + Janus.randomString(12),
                                    success: pluginHandle => {
                                        this.videoroom = pluginHandle;
                                        this.register(pluginHandle);
                                    },
                                    error: (error) => {
                                        this.restart = true;
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
                                    onmessage: this.onMessage.bind(this),
                                    onremotetrack: (track, mid, on, metadata) => {
                                        Janus.debug(
                                            "Remote track (mid=" + mid + ") " +
                                            (on ? "added" : "removed") +
                                            (metadata ? " (" + metadata.reason + ") " : "") + ":", track
                                        );
                                        if (!on) {
                                            // Track removed, get rid of the stream and the rendering
                                          delete this.remoteStreams[mid];
                                            return;
                                        }
                                        if (this.remoteStreams.hasOwnProperty(mid) || track.kind !== "video") {
                                            return;
                                        }
                                        this.remoteStreams[mid] = track;
                                        if (this.remoteStream) {
                                            return;
                                        }
                                        this.remoteStream = new MediaStream([track]);
                                        this.remoteStream.mid = mid;
                                        Log.debug(this.remoteStream);
                                        Janus.attachMediaStream(
                                            this.remoteVideo || document.getElementById('deft_venue_remote_video'),
                                            this.remoteStream
                                        );
                                    }
                                }
                            );
                        },
                        error: function(error) {
                            this.restart = true;
                            Log.debug(error);
                        }
                    }
                );
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
        return Ajax.call([{
            args: {
                handle: pluginHandle.getId(),
                id: Number(this.peerid),
                plugin: pluginHandle.plugin,
                room: this.roomid,
                ptype: false,
                feed: this.feed,
                session: pluginHandle.session.getSessionId()
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_join_room'
        }])[0];
    }

    onLocalTrack() {
        return;
    }

    onMessage(msg, jsep) {
        Log.debug(msg);
                    const pluginHandle = this.videoroom;
        Janus.debug(" ::: Got a message :::", msg);
        const event = msg.videoroom;
        Janus.debug("Event: " + event);
        switch (event) {
            case 'joined':
                // Successfully joined, negotiate WebRTC now
                if (msg.id) {
                    this.peerid = msg.id;
                    Janus.log("Successfully joined room " + msg.room + " with ID " + this.peerid);
                    if (!this.webrtcUp) {
                        const tracks = [{
                            type: 'video',
                            capture: true,
                            recv: false
                        }];
                        this.webrtcUp = true;
                        pluginHandle.createOffer({
                            // We only want bidirectional audio
                            tracks: tracks,
                            success: (jsep) => {
                                Janus.debug("Got SDP!", jsep);
                                const publish = {
                                    request: "configure",
                                    video: true,
                                    audio: false
                                };
                                pluginHandle.send({
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
                }
                break;
            case 'destroyed':
                // The room has been destroyed
                Janus.warn("The room has been destroyed!");
                Notification.alert('', "The room has been destroyed", function() {
                    window.close();
                });
                break;
            case 'attached':
                this.creatingSubscription = false;
                break;
            case 'event':
                if (msg.error) {
                    if (msg.error_code === 485) {
                        // This is a "no such room" error: give a more meaningful description
                        Notification.alert(
                            "<p>Apparently room <code>" + this.roomid + "</code> is not configured</p>"
                        );
                    } else if (msg.error_code === 428) {
                        this.restart = true;
                    } else {
                        Notification.alert(msg.error_code, msg.error);
                    }
                    return;
                }
                break;
        }
        if (jsep) {
            Janus.debug("Handling SDP as well...", jsep);
            // Answer and attach
            pluginHandle.createAnswer(
                {
                    jsep: jsep,
                    tracks: [
                        {type: 'data'}
                    ],
                    success: function(jsep) {
                        Janus.debug("Got SDP!");
                        Janus.debug(jsep);
                        let body = {request: "start", room: this.roomid};
                        pluginHandle.send({message: body, jsep: jsep});
                    },
                    error: function(error) {
                        Janus.error("WebRTC error:", error);
                        Notification.alert("WebRTC error... ", error.message);
                    }
                }
            );
        }
    }
}
