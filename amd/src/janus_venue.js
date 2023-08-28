/**
 * Manage venue connections
 *
 * @module     block_deft/venue_manager
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import {get_string as getString} from 'core/str';
import Janus from 'block_deft/janus-gateway';
import Log from "core/log";
import Notification from "core/notification";
import Publish from 'block_deft/publish';
import Subscribe from 'block_deft/subscribe';
import * as Toast from 'core/toast';
import VenueManager from "block_deft/venue_manager";

var publish = null,
    contextid = 0,
    iceServers = '',
    roomid = 0,
    peerid = 0,
    server = '',
    stereo = false;

export default class JanusManager extends VenueManager {

    /**
     * Start to establish the peer connections
     */
    startConnection() {
        this.transactions = {};
        roomid = this.roomid;
        peerid = this.peerid;
        server = this.server;
        contextid = this.contextid;
        iceServers = this.iceServers;

        // Initialize the library (all console debuggers enabled)
        Janus.init({
            debug: "none", callback: () => {
                // Create session.
                this.janus = new Janus(
                    {
                        server: this.server,
                        iceServers: this.iceServers,
                        success: () => {
                            // Attach audiobridge plugin.
                            this.janus.attach(
                                {
                                    plugin: "janus.plugin.audiobridge",
                                    opaqueId: "audioroom-" + Janus.randomString(12),
                                    success: pluginHandle => {
                                        this.audioBridge = pluginHandle;
                                        Log.debug(pluginHandle.session.getSessionId());
                                        this.register(pluginHandle);
                                    },
                                    error: function(error) {
                                        Janus.error("  -- Error attaching plugin...", error);
                                        Notification.alert('', "Error attaching plugin... " + error);
                                    },
                                    onmessage: this.onMessage.bind(this),
                                    onremotetrack: (track, mid, on, metadata) => {
                                        Janus.debug(
                                            "Remote track (mid=" + mid + ") " +
                                            (on ? "added" : "removed") +
                                            (metadata ? " (" + metadata.reason + ") " : "") + ":", track
                                        );
                                        if (this.remoteStream || track.kind !== "audio") {
                                            return;
                                        }
                                        if (!on) {
                                            // Track removed, get rid of the stream and the rendering
                                            this.remoteStream = null;
                                            return;
                                        }
                                        this.remoteStream = new MediaStream([track]);
                                        Janus.attachMediaStream(document.getElementById('roomaudio'), this.remoteStream);
                                    }
                                }
                            );
                            this.janus.attach(
                                {
                                    plugin: "janus.plugin.textroom",
                                    opaqueId: "textroom-" + Janus.randomString(12),
                                    success: pluginHandle => {
                                        this.textroom = pluginHandle;
                                        Janus.log("Plugin attached! (" + this.textroom.getPlugin()
                                            + ", id=" + this.textroom.getId() + ")");
                                        // Setup the DataChannel
                                        const body = {request: "setup"};
                                        Janus.debug("Sending message:", body);
                                        this.textroom.send({message: body});
                                    },
                                    error: function(error) {
                                        Notification.alert('', error);
                                        Janus.error("  -- Error attaching plugin...", error);
                                    },
                                    onmessage: (msg, jsep) => {
                                        Janus.debug(" ::: Got a message :::", msg);
                                        if (msg.error) {
                                            Notification.alert(msg.error_code, msg.error);
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
                                                        Janus.debug("Got SDP!", jsep);
                                                        const body = {request: "ack"};
                                                        this.textroom.send({message: body, jsep: jsep});
                                                    },
                                                    error: function(error) {
                                                        Janus.error("WebRTC error:", error);
                                                    }
                                                }
                                            );
                                        }
                                    },
                                    // eslint-disable-next-line no-unused-vars
                                    ondataopen: (label, protocol) => {
                                        const transaction = Janus.randomString(12),
                                            register = {
                                                textroom: "join",
                                                transaction: transaction,
                                                room: this.roomid,
                                                username: String(this.peerid),
                                                display: '',
                                            };
                                        this.textroom.data({
                                            text: JSON.stringify(register),
                                            error: function(reason) {
                                                Notification.alert('Error', reason);
                                            }
                                        });
                                    },
                                    ondata: (data) => {
                                        Janus.debug("We got data from the DataChannel!", data);
                                        const message = JSON.parse(data),
                                            event = message.textroom,
                                            transaction = message.transaction;
                                        if (transaction && this.transactions[transaction]) {
                                            this.transactions[transaction](message);
                                            delete this.transactions[transaction];
                                        }

                                        if (event === 'message' && message.from != this.peerid) {
                                            this.handleMessage(message.from, {data: message.text});
                                        }
                                        if (event === 'join') {
                                            this.sendMessage(JSON.stringify({
                                                "raisehand": !!document.querySelector(
                                                    '[data-peerid="' + this.peerid + '"] a.hidden[data-action="raisehand"]'
                                                )
                                            }));
                                        }
                                    }
                                }
                            );
                        },
                        error: (error) => {
                            getString('serverlost', 'block_deft').done((message) => {
                                Toast.add(message, {'type': 'info'});
                            });
                            Log.debug(error);
                            this.restart = true;
                            if (publish) {
                                publish.handleClose();
                                publish = null;
                            }
                            if (this.remoteFeed) {
                                this.remoteFeed.handleClose();
                                this.remoteFeed = null;
                            }
                            document.querySelectorAll(
                                '[data-region="deft-venue"] video'
                            ).forEach(video => {
                                const newfeed = document.createElement('video');
                                video.classList.add('hidden');
                                video.srcObject = null;
                                video.parentNode.insertBefore(newfeed, video);
                                video.remove();
                                newfeed.classList.add('w-100');
                                newfeed.id = 'deft_venue_remote_video';
                                newfeed.setAttribute('controls', true);
                                newfeed.setAttribute('autoplay', true);
                            });
                            document.querySelectorAll(
                                '[data-region="deft-venue"] [data-action="publish"],'
                                + '[data-region="deft-venue"] [data-action="unpublish"]'
                            ).forEach(button => {
                                if (button.getAttribute('data-action') == 'publish') {
                                    button.classList.remove('hidden');
                                } else {
                                    button.classList.add('hidden');
                                }
                            });
                        },
                        destroyed: function() {
                            window.close();
                        }
                    }
                );
            }
        });

        document.querySelector('body').removeEventListener('venueclosed', this.handleClose.bind(this));
        document.querySelector('body').addEventListener('venueclosed', this.handleClose.bind(this));

        document.querySelector('body').removeEventListener('click', handleClick);
        document.querySelector('body').addEventListener('click', handleClick);
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
                session: pluginHandle.session.getSessionId(),
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_join_room'
        }])[0];
    }

    /**
     * Handle plugin message
     *
     * @param {object} msg msg
     * @param {string} jsep
     */
    onMessage(msg, jsep) {
        const event = msg.audiobridge;
        if (event) {
            if (event === "joined") {
                // Successfully joined, negotiate WebRTC now
                if (msg.id) {
                    Janus.log("Successfully joined room " + msg.room + " with ID " + this.peerid);
                    if (!this.webrtcUp) {
                        this.webrtcUp = true;
                        this.audioInput.then(audioStream => {
                            // Publish our stream.
                            const tracks = [];
                            if (audioStream) {
                                audioStream.getAudioTracks().forEach(track => {
                                    tracks.push({
                                        type: 'audio',
                                        capture: track,
                                        recv: true
                                    });
                                });
                            } else {
                                tracks.push({
                                    type: 'audio',
                                    capture: true,
                                    recv: true
                                });
                            }
                            this.audioBridge.createOffer({
                                // We only want bidirectional audio
                                tracks: tracks,
                                customizeSdp: function(jsep) {
                                    if (stereo && jsep.sdp.indexOf("stereo=1") == -1) {
                                        // Make sure that our offer contains stereo too
                                        jsep.sdp = jsep.sdp.replace("useinbandfec=1", "useinbandfec=1;stereo=1");
                                    }
                                },
                                success: (jsep) => {
                                    Janus.debug("Got SDP!", jsep);
                                    const publish = {request: "configure", muted: false};
                                    this.audioBridge.send({message: publish, jsep: jsep});
                                },
                                error: function(error) {
                                    Janus.error("WebRTC error:", error);
                                    Notification.alert("WebRTC error... ", error.message);
                                }
                            });

                            return audioStream;
                        }).catch(Notification.exception);
                    }
                }
                // Any room participant?
                if (msg.participants) {
                    this.updateParticipants(msg.participants);
                }
            } else if (event === "destroyed") {
                // The room has been destroyed
                Janus.warn("The room has been destroyed!");
                Notification.alert('', "The room has been destroyed");
            } else if (event === "event") {
                if (msg.participants) {
                    this.updateParticipants(msg.participants);
                } else if (msg.error) {
                    if (msg.error_code === 485) {
                        // This is a "no such room" error: give a more meaningful description
                        Notification.alert(
                            "<p>Room <code>" + this.roomid + "</code> is not configured."
                        );
                    } else {
                        Notification.alert(msg.error_code, msg.error);
                    }
                    return;
                }
                if (msg.leaving) {
                    // One of the participants has gone away?
                    const leaving = msg.leaving;
                    Janus.log(
                        "Participant left: " + leaving
                    );
                    document.querySelectorAll('#deft_audio [peerid="' + leaving + '"]').forEach(peer => {
                        peer.remove();
                    });
                }
            }
        }
        if (jsep) {
            Janus.debug("Handling SDP as well...", jsep);
            this.audioBridge.handleRemoteJsep({jsep: jsep});
        }
    }

    processSignal() {
        return;
    }

    /**
     * Update participants display for audio bridge
     *
     * @param {array} list List of participants returned by plugin
     */
    updateParticipants(list) {
        Janus.debug("Got a list of participants:", list);
        for (const f in list) {
            const id = list[f].id,
                display = list[f].display,
                setup = list[f].setup,
                muted = list[f].muted;
            Janus.debug("  >> [" + id + "] " + display + " (setup=" + setup + ", muted=" + muted + ")");
            if (!document.querySelector('#deft_audio [peerid="' + id + '"]') && Number(this.peerid) != Number(id)) {
                // Add to the participants list
                Log.debug(this.peerid);
                Log.debug(id);
                this.peerAudioPlayer(id);
            }
        }
    }

    /**
     * Transfer signals with signal server
     */
    sendSignals() {

        if (this.throttled || !navigator.onLine) {
            return;
        }

        const time = Date.now();
        if (this.lastUpdate + 200 > time) {
            this.throttled = true;
            setTimeout(() => {
                this.throttled = false;
            }, this.lastUpdate + 250 - time);
            this.sendSignals();
            return;
        }
        this.lastUpdate = time;

        Ajax.call([{
            args: {
                contextid: this.contextid,
                lastsignal: 0,
                messages: [],
            },
            contextid: this.contextid,
            done: response => {
                response.settings.forEach(peer => {
                    if (peer.id == Number(this.peerid)) {
                        if (peer.status) {
                            // Release microphone.
                            clearInterval(this.meterId);
                            this.audioInput.then(audioStream => {
                                if (audioStream) {
                                    audioStream.getAudioTracks().forEach(track => {
                                        track.stop();
                                    });
                                }
                                return audioStream;
                            }).catch(Log.debug);

                            // Close connections.
                            this.janus.destroy();

                            document.querySelectorAll(
                                '[data-region="deft-venue"] [data-peerid="' + this.peerid
                                + '"], [data-region="deft-venue"] [data-action="publish"]'
                            ).forEach(venue => {
                                const e = new Event('venueclosed', {bubbles: true});
                                venue.dispatchEvent(e);
                            });

                            this.socket.disconnect();

                            window.close();
                            return;
                        }
                        this.mute(peer.mute);
                    }
                    document.querySelectorAll(
                        '[data-peerid="' + peer.id + '"] [data-action="mute"], [data-peerid="' + peer.id
                            + '"] [data-action="unmute"]'
                    ).forEach(button => {
                        if (peer.mute == (button.getAttribute('data-action') == 'mute')) {
                            button.classList.add('hidden');
                        } else {
                            button.classList.remove('hidden');
                        }
                    });
                    if (
                        !response.peers.includes(Number(peer.id))
                        && document.querySelector('#deft_audio [data-peerid="' + peer.id + '"]')
                    ) {
                        document.querySelector('#deft_audio [data-peerid="' + peer.id + '"]').remove();
                    }
                });
                if (!response.peers.includes(Number(this.peerid))) {
                    return;
                }
                for (const key in Object.keys(this.peerConnections)) {
                    if (!response.peers.includes(Number(key)) && this.peerConnections[key]) {
                        const pc = this.peerConnections[key];
                        pc.close();
                    }
                }
                if (response.peers.includes(Number(response.feed))) {
                    this.subscribeTo(response.feed);
                    document.querySelectorAll('[data-region="deft-venue"] video').forEach(video => {
                        video.classList.remove('hidden');
                    });
                } else {
                    this.subscribeTo(0);
                    document.querySelectorAll('[data-region="deft-venue"] video').forEach(video => {
                        video.classList.add('hidden');
                    });
                }
                if (this.restart) {
                    getString('reconnecting', 'block_deft').done((message) => {
                        Toast.add(message, {'type': 'info'});
                    });
                    Ajax.call([{
                        args: {
                            id: Number(this.peerid),
                            publish: false,
                            room: this.roomid
                        },
                        contextid: this.contextid,
                        fail: Notification.exception,
                        methodname: 'block_deft_publish_feed'
                    }]);
                    this.restart = false;
                    publish = null;
                    this.startConnection();
                }
            },
            fail: Notification.exception,
            methodname: 'block_deft_send_signal'
        }]);
    }

    /**
     * Send a message through data channel to peers
     *
     * @param {string} text
     */
    sendMessage(text) {
        if (text && text !== "" && this.textroom) {
            const message = {
                textroom: "message",
                transaction: Janus.randomString(12),
                room: this.roomid,
                text: text
            };
            this.textroom.data({
                text: JSON.stringify(message),
                error: Log.debug,
            });
        }
    }

    /**
     * Subscribe to feed
     *
     * @param {int} source Feed to subscribe
     */
    subscribeTo(source) {

        if (this.remoteFeed && !this.remoteFeed.creatingSubscription && !this.remoteFeed.restart) {
            const update = {
                request: 'update',
                subscribe: [{
                    feed: Number(source)
                }],
                unsubscribe: [{
                    feed: Number(this.remoteFeed.current)
                }]
            };

            if (!source && this.remoteFeed.current) {
                delete update.subscribe;
            } else if (source && !this.remoteFeed.current) {
                delete update.unsubscribe;
            }

            if (this.remoteFeed.current != source) {
                this.remoteFeed.videoroom.send({message: update});
                if (this.remoteFeed.current == publish.feed) {
                    publish.handleClose();
                    publish = null;
                }
                this.remoteFeed.current = source;
            }
        } else if (this.remoteFeed && this.remoteFeed.restart) {
            if (this.remoteFeed.current != source) {
                this.remoteFeed = null;
                this.subscribeTo(source);
            }
        } else if (this.remoteFeed) {
            setTimeout(() => {
                this.subscribeTo(source);
            }, 500);
        } else if (source) {
            this.remoteFeed = new Subscribe(this.contextid, this.iceservers, this.roomid, this.server, this.peerid);
            this.remoteFeed.startConnection(source);
        }
    }

    /**
     * Close connection when peer removed
     */
    handleClose() {
        if (this.janus) {
            this.janus.destroy();
            this.janus = null;
        }

        if (publish) {
            publish.handleClose();
            publish.unpublish();
            publish = null;
        }
        document.querySelector('body').removeEventListener('click', handleClick);


        if (this.remoteFeed && this.remoteFeed.janus) {
            this.remoteFeed.janus.destroy();
            this.remoteFeed = null;
        }
    }
}

/**
 * Handle click event
 *
 * @param {Event} e
 */
const handleClick = function(e) {
    const button = e.target.closest(
        '[data-region="deft-venue"] [data-action="publish"], [data-region="deft-venue"] [data-action="unpublish"]'
    );
    if (publish) {
        publish.handleClick(e);
    } else if (button) {
        const action = button.getAttribute('data-action'),
            type = button.getAttribute('data-type');
        publish = new Publish(contextid, iceServers, roomid, server, peerid);
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
        if (type == 'display') {
            publish.shareDisplay();
        } else {
            publish.shareCamera();
        }
        publish.startConnection();
    }
};
