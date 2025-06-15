// This file is part of Moodle - http://moodle.org/ //
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

/*
 * Deft response Jitsi integration venue manager
 *
 * @package    block_deft
 * @module     block_deft/jitsi/venue_manager
 * @copyright  2025 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
var domain;
var connection;

import Ajax from "core/ajax";
import Fragment from 'core/fragment';
import JitsiMeetJS from "block_deft/jitsi/lib-jitsi-meet.min";
import Log from "core/log";
import Notification from "core/notification";
import Socket from "block_deft/jitsi/socket";
import Templates from "core/templates";
import VenueManager from "block_deft/venue_manager";

export default class MediaManager extends VenueManager {
    /**
     * Initialize player plugin
     *
     * @param {int} contextid
     * @param {string} server Jitsi server to use
     * @param {string} room Room name
     * @param {object} userinfo User information to pass to meeting
     * @param {string} jwt JWT authentication token
     * @param {int} peerid Peer ID
     *
     * @returns {bool}
     */
    constructor(contextid, server, room, userinfo, jwt, peerid) {

        super(contextid, '', [], '', true, true, 14400, 0, '');
        this.contextid = contextid;
        domain = server;
        this.userinfo = [];
        this.peerid = peerid;
        this.displayedTracks = [];
        this.videoTracks = {};
        this.audioTracks = {};

        JitsiMeetJS.init();
        JitsiMeetJS.setLogLevel(JitsiMeetJS.logLevels.DEBUG);
        if (connection) {
            connection.disconnect();
        }

        connection = new JitsiMeetJS.JitsiConnection(null, jwt, {
            serviceUrl: `https://${ domain }/http-bind`,
            hosts: {
                domain: domain,
                muc: `conference.${ domain }`
            }
        });
        connection.addEventListener(JitsiMeetJS.events.connection.CONNECTION_ESTABLISHED, () => {
            this.room = connection.initJitsiConference(room, {
                disableSimulcast: true
            });
            this.room.addEventListener(JitsiMeetJS.events.conference.TRACK_ADDED, track => {
                this.onRemoteTrack(track);
            });
            this.room.addEventListener(JitsiMeetJS.events.conference.TRACK_REMOVED, track => {
                if (track.getType() == 'video') {
                    this.videoTracks[track.getParticipantId()] = null;
                } else {
                    this.audioTracks[track.getParticipantId()] = null;
                }
                track.dispose();
            });
            this.room.addCommandListener('updateinterface', e => {
                this.handleMessage(e.attributes.id, {
                    data: e.attributes.message
                });
            });

            this.socket = new Socket(this.room);
            this.socket.subscribe(() => {
                this.sendSignals();
            });
            this.room.on(JitsiMeetJS.events.conference.CONFERENCE_JOINED, async() => {
                const tracks = await JitsiMeetJS.createLocalTracks({
                    devices: ['audio'],
                });
                tracks.forEach(track => {
                    if (this[`${ track.getType() }Track`]) {
                        this.room.replaceTrack(this[`${ track.getType() }Track`], track);
                    } else {
                        this.room.addTrack(track);
                    }
                    this[`${ track.getType() }Track`] = track;

                    this.monitorVolume(track.stream);
                });
                this.register();
            });

            document.body.addEventListener(
                'venueclosed',
                () => {
                    this.closeConnections();
                }
            );

            this.room.join();

            document.body.addEventListener('click', e => this.handleClick(e));
        });
        connection.addEventListener(JitsiMeetJS.events.connection.CONNECTION_DISCONNECTED, () => {
            window.close();
        });

        connection.connect();

        this.addListeners();

        return true;
    }

    /**
     * Start to establish the peer connections
     */
    startConnection() {
    }

    /**
     * Update motions
     *
     * @param {int} contextid
     */
    async updateMotions(contextid) {
        const selector = `[data-contextid="${contextid}"][data-region="plenum-motions"]`;
        const content = document.querySelector(selector);
        if (content) {
            const response = await Ajax.call([{
                args: {
                    contextid: contextid
                },
                contextid: contextid,
                fail: Notification.exception,
                methodname: 'plenumform_jitsi2_update_content'
            }])[0];
            if (response.motions) {
                Templates.replaceNodeContents(content, response.motions, response.javascript);
                this.userinfo = response.userinfo;
                this.updateMedia();
            }
            if (response.controls) {
                const selector = `[data-contextid="${contextid}"][data-region="plenum-deft-controls"]`;
                Templates.replaceNodeContents(selector, response.controls, '');
            }
            if (!response.sharevideo) {
                this.room.getLocalTracks().forEach(track => {
                    track.dispose();
                });
            }
        }
    }

    /**
     * Attach or detach media
     */
    updateMedia() {
        return;
        this.displayedTracks.forEach(async track => {
            if (!this.userinfo.find(speaker => speaker.id == track.getParticipantId())) {
                document.querySelectorAll(
                    `[data-region="slot-${ track.role }"] ${ track.getType() }`
                ).forEach(player => {
                    track.detach(player);
                });
                delete this.displayedTracks[this.displayedTracks.indexOf(track)];
            }
        });
        this.userinfo.forEach(speaker => {
            if (this.videoTracks[speaker.id]) {
                const track = this.videoTracks[speaker.id];
                if (!this.displayedTracks.includes(track)) {
                    track.role = speaker.role;
                    track.attach(document.querySelector(`[data-region="slot-${ speaker.role }"] ${ track.getType() }`));
                    this.displayedTracks.push(track);
                }
            }
            if (this.audioTracks[speaker.id]) {
                const track = this.audioTracks[speaker.id];
                if (!this.displayedTracks.includes(track)) {
                    track.role = speaker.role;
                    track.attach(document.querySelector(`[data-region="slot-${ speaker.role }"] ${ track.getType() }`));
                    this.displayedTracks.push(track);
                }
            }
            document.querySelectorAll(`[data-region="slot-${ speaker.role }"] .card-header`).forEach(function(h) {
                h.innerHTML = speaker.name;
            });
            document.querySelectorAll(`[data-region="slot-${ speaker.role }"] video`).forEach(function(video) {
                video.poster = speaker.pictureurl;
            });
        });
    }

    /**
     * Process new remote track
     *
     * @param {JitsiTrack} track New track
     */
    onRemoteTrack(track) {
        Log.debug(track);
        if (track.getType() == 'video') {
            this.videoTracks[track.getParticipantId()] = track;
        } else {
            this.audioTracks[track.getParticipantId()] = track;
        }
        this.updateMedia();
    }

    /**
     * Change published media in activity
     *
     * @param {bool} publish Whether to add or remove media
     */
    async publish(publish) {
        await Ajax.call([{
            args: {
                //id: this.room.myUserId(),
                id: this.peerid,
                publish: publish,
                room: 0
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_publish_feed'
        }])[0];
        this.socket.notify();
    }

    /**
     * Register the room
     *
     * @return {Promise}
     */
    async register() {
        // Try a registration
        const response = await Ajax.call([{
            args: {
                handle: 0,
                id: Number(this.peerid),
                //plugin: pluginHandle.plugin,
                plugin: this.room.myUserId(),
                room: 0,
                session: 0,
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'block_deft_join_room'
        }])[0];

        if (response.status) {
        }
            this.socket.notify();

        return response;
    }

    /**
     * Handle button click
     *
     * @param {Event} e Click event
     */
    async handleClick(e) {
        const button = e.target.closest('a[data-action="publish"], a[data-action="unpublish"]');

        if (!button) {
            return;
        }
        e.stopPropagation();
        e.preventDefault();

        if (button.dataset.action == 'publish') {
            if (!this.audioTrack) {
                return;
            }
            const tracks = await JitsiMeetJS.createLocalTracks({
                devices: ['video'],
                constraints: {aspectRatio: {exact: 1}, height: {ideal: 360}, width: {ideal: 360}}
            });
            tracks.forEach(track => {
                if (this[`${ track.getType() }Track`]) {
                    this.room.replaceTrack(this[`${ track.getType() }Track`], track);
                } else {
                    this.room.addTrack(track);
                }
                this[`${ track.getType() }Track`] = track;
            });
            this.publish(true);
            document.querySelectorAll('a[data-action="publish"]').forEach(button => {
                button.classList.add('hidden');
            });
            document.querySelectorAll('a[data-action="unpublish"]').forEach(button => {
                button.classList.remove('hidden');
            });
        } else {
            document.querySelectorAll('a[data-action="publish"]').forEach(button => {
                button.classList.remove('hidden');
            });
            document.querySelectorAll('a[data-action="unpublish"]').forEach(button => {
                button.classList.add('hidden');
            });
            if (this.videoTrack) {
                this.videoTrack.dispose();
                this.videoTrack = null;
            }
            this.publish(false);
        }
    }

    /**
     * Change mute status
     *
     * @param {bool} state State to be set
     */
    mute(state) {
        if (this.audioTrack) {
            this.audioTrack.track.enabled = !state;
        }
    }

    processSignal() {
        return;
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
                this.subscribeTo(response.feed);
                response.settings.forEach(peer => {
                    if (peer.id == Number(this.peerid)) {
                        if (peer.status) {
                            // Release microphone.
                            clearInterval(this.meterId);
                            if (this.audioTrack) {
                                this.audioTrack.track.stop();
                            }
                            // Close connections.
                            document.querySelectorAll(
                                '[data-region="deft-venue"] [data-peerid="' + this.peerid
                                + '"], [data-region="deft-venue"] [data-action="publish"]'
                            ).forEach(venue => {
                                const e = new Event('venueclosed', {bubbles: true});
                                venue.dispatchEvent(e);
                            });

                            this.socket.disconnect();

                            this.closeConnections();

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
                    if (!document.querySelector('#deft_audio [data-peerid="' + peer.id + '"]')
                        && this.audioTracks[peer.username]
                        && peer.id != this.peerid
                    ) {
                        this.peerAudioPlayer(peer);
                    }
                });
                if (!response.peers.includes(Number(this.peerid))) {
                    return;
                }
                if (response.peers.includes(Number(response.feed))) {
                } else {
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
        if (text) {
            this.room.sendCommandOnce('updateinterface', {
                value: 'updateinterface',
                attributes: {
                    id: this.peerid,
                    message: text
                },
                children: []
            });
        }
    }

    /**
     * Return audio player for peer
     *
     * @param {object} peer Peer information
     * @returns {Promise} Resolve to audio player node
     */
    peerAudioPlayer(peer) {
        const usernode = document.querySelector('#deft_audio div[data-peerid="' + peer.id + '"] audio');
        if (usernode) {
            return Promise.resolve(usernode);
        } else {
            const node = document.createElement('div');
            node.setAttribute('data-peerid', peer.id);
            if (document.querySelector('body#page-blocks-deft-venue')) {
                node.setAttribute('class', 'col col-12 col-sm-6 col-md-4 col-lg-3 p-2');
            } else {
                node.setAttribute('class', 'col col-12 col-sm-6 col-md-4 p-2');
            }
            window.setTimeout(() => {
                node.querySelectorAll('img.card-img-top').forEach(image => {
                    image.setAttribute('height', null);
                    image.setAttribute('width', null);
                });
            });
            return Fragment.loadFragment(
                'block_deft',
                'venue',
                this.contextid,
                {
                    peerid: peer.id
                }
            ).done((userinfo) => {
                if (!document.querySelector('#deft_audio div[data-peerid="' + peer.id + '"] audio')) {
                    document.querySelector('#deft_audio').appendChild(node);
                    node.innerHTML = userinfo;
                }
            }).then(() => {
                const audio = document.querySelector('#deft_audio div[data-peerid="' + peer.id + '"] audio');
                if (audio) {
                    const track = this.audioTracks[peer.username];
                    track.attach(audio);
                }
                return audio;
            }).catch(Notification.exception);
        }
    }

    /**
     * Add event listeners
     */
    addListeners() {

        document.querySelector('body').removeEventListener('click', this.handleMuteButtons.bind(this));
        document.querySelector('body').addEventListener('click', this.handleMuteButtons.bind(this));

        document.querySelector('body').removeEventListener('click', this.handleRaiseHand.bind(this));
        document.querySelector('body').addEventListener('click', this.handleRaiseHand.bind(this));

        document.querySelector('body').removeEventListener('click', this.closeConnections.bind(this));
        document.querySelector('body').addEventListener('click', this.closeConnections.bind(this));

        window.onbeforeunload = this.closeConnections.bind(this);
    }

    /**
     * Handle click for mute
     *
     * @param {Event} e Button click
     */
    async handleMuteButtons(e) {
        const button = e.target.closest(
            'a[data-action="mute"], a[data-action="unmute"]'
        );
        if (button) {
            const action = button.getAttribute('data-action'),
                peerid = button.closest('[data-peerid]').getAttribute('data-peerid');
            e.stopPropagation();
            e.preventDefault();
            if (peerid == this.peerid) {
                this.mute(action == 'mute');
            }
            await Ajax.call([{
                args: {
                    mute: action == 'mute',
                    peerid: peerid,
                    "status": false
                },
                fail: Notification.exception,
                methodname: 'block_deft_venue_settings'
            }]);
            button.closest('[data-peerid]').querySelectorAll('[data-action="mute"], [data-action="unmute"]').forEach(option => {
                if (option.getAttribute('data-action') == action) {
                    option.classList.add('hidden');
                } else {
                    option.classList.remove('hidden');
                }
            });
            this.socket.notify();
        }
    }

    /**
     * Shut down gracefully before closing
     *
     * @param {Event} e Button click
     */
    async closeConnections(e) {
        if (e && e.type == 'click') {
            const button = e.target.closest('[data-region="deft-venue"] a[data-action="close"]');
            if (button) {
                e.stopPropagation();
                e.preventDefault();
            } else {
                return;
            }
        }
        document.querySelectorAll('[data-region="deft-venue"] a[data-action="close"] i').forEach(button => {
            button.classList.add('bg-danger');
        });
        if (this.room) {
            this.room.getLocalTracks().forEach(track => {
                track.dispose();
            });
        }
        document.querySelector('body').classList.remove('block_deft_raisehand');
        await Ajax.call([{
            args: {
                mute: false,
                "status": true
            },
            fail: Notification.exception,
            methodname: 'block_deft_venue_settings'
        }]);

        // Release microphone.
        clearInterval(this.meterId);

        // Close connections.
        connection.disconnect();

        document.querySelectorAll('[data-region="deft-venue"] [data-peerid="' + this.peerid + '"]').forEach(venue => {
            const event = new Event('venueclosed');
            venue.dispatchEvent(event);
        });

        window.beforeunload = null;

        this.sendSignals();
    }

    /**
     * Subscribe to feed
     *
     * @param {int} source Feed to subscribe
     */
    subscribeTo(source) {
        Log.debug(source);
        if (!source || !this.videoTracks[source]) {
            document.querySelectorAll('[data-region="deft-venue"] video').forEach(video => {
                video.classList.add('hidden');
            });
            Log.debug('no source');
            this.currentFeed = null;

            return;
        }

        if (this.currentFeed == source) {
            Log.debug('no change');
            return;
        }
        Log.debug(this.currentFeed);
        this.currentFeed = source;

        const track = this.videoTracks[source];
        document.querySelectorAll('[data-region="deft-venue"] video').forEach(video => {
            track.attach(video);
            video.classList.remove('hidden');
        });
    }
}
