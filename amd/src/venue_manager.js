/*
 * Manage venue connections
 *
 * @package    block_deft
 * @module     block_deft/venue_manager
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Fragment from 'core/fragment';
import {get_string as getString} from 'core/str';
import JitsiMeetJS from "block_deft/jitsi/lib-jitsi-meet.min";
import ModalEvents from 'core/modal_events';
import Notification from "core/notification";
import Log from "core/log";
import Socket from "block_deft/socket";
import JitsiSocket from "block_deft/jitsi/socket";
import adapter from "core/adapter";

export default class VenueManager {

    /**
     * Listen for comment actions
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token
     * @param {array} peers
     * @param {int} peerid My peer id
     * @param {array} iceServers ICE server array to configure peers
     * @param {bool} autogaincontrol
     * @param {bool} echocancellation
     * @param {bool} noisesuppression
     * @param {int} samplerate
     * @param {int} roomid
     * @param {string} server
     */
    constructor(
        contextid, token, peers, peerid, iceServers, autogaincontrol,
        echocancellation, noisesuppression, samplerate, roomid, server
    ) {
        this.contextid = contextid;
        this.token = token;
        this.peerid = peerid;
        this.iceServers = iceServers;
        this.autogaincontrol = autogaincontrol;
        this.echocancellation = echocancellation;
        this.noisesuppression = noisesuppression;
        this.roomid = roomid;
        this.samplerate = samplerate;
        this.lastSignal = 0;
        this.lastUpdate = 0;
        this.dataChannels = [];
        this.peerConnections = {};
        this.queueout = [];
        this.ignoreOffer = new Set();
        this.makingOffer = new Set();
        this.peers = peers;
        this.server = server;

        window.adapter = adapter;

        if (!window.RTCPeerConnection) {
            document.querySelectorAll('.venue_manager').forEach((venue) => {
                const e = new Event('venueclosed', {bubbles: true});
                venue.dispatchEvent(e);
            });
            Notification.alert(
                getString('unsupportedbrowser', 'block_deft'),
                getString('unsupportedbrowsermessage', 'block_deft')
            ).then(notice => {
                const root = notice.getRoot();
                root.on(ModalEvents.cancel, () => {
                    return Ajax.call([{
                        args: {
                            mute: false,
                            "status": true
                        },
                        done: (status) => {
                            window.close();
                            return status;
                        },
                        fail: Notification.exception,
                        methodname: 'block_deft_venue_settings'
                    }]);
                });

                return notice;
            }).fail(Notification.exception);

            return;
        }

        this.addListeners();

        this.startConnection();
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
        document.body.dispatchEvent(new CustomEvent('deftaction', { }));

        this.audioInput = navigator.mediaDevices.getUserMedia({
            audio: {
                autoGainControl: this.autogaincontrol,
                echoCancellation: this.echocancellation,
                noiseSuppression: this.noisesuppression,
                sampleRate: this.samplerate
            },
            video: false
        }).catch((e) => {
            Log.debug(e);

            Ajax.call([{
                args: {
                    mute: true,
                    "status": false
                },
                fail: Notification.exception,
                methodname: 'block_deft_venue_settings'
            }]);

            return false;
        });
        this.audioInput.then(this.monitorVolume.bind(this)).catch(Log.debug);

        if (!this.token) {
            return;
        }

        if (!this.roomid) {
            this.socket = new Socket(this.contextid, this.token);
            this.socket.subscribe(() => {
                this.sendSignals();
            });

            return;
        }
        const domain = this.server;

        JitsiMeetJS.init();
        JitsiMeetJS.setLogLevel(JitsiMeetJS.logLevels.DEBUG);

        this.connection = new JitsiMeetJS.JitsiConnection(null, this.token, {
            serviceUrl: `https://${ domain }/http-bind`,
            hosts: {
                domain: domain,
                muc: `conference.${ domain }`
            }
        });
        this.connection.addEventListener(JitsiMeetJS.events.connection.CONNECTION_ESTABLISHED, () => {
            this.room = this.connection.initJitsiConference(this.roomid, {
                disableSimulcast: true
            });

            this.socket = new JitsiSocket(this.room);
            this.socket.subscribe(() => {
                this.sendSignals();
            });
            document.body.addEventListener('deftaction', () => {
                this.socket.notify();
            });

            this.room.join();
        });

        this.connection.connect();
    }

    /**
     * Start to establish the peer connections
     */
    startConnection() {
        this.peers.forEach(peerid => {
            const pc = new RTCPeerConnection({
                 iceServers: this.iceServers
            }),
                dataChannel = pc.createDataChannel('Events');
            this.dataChannels.push(dataChannel);
            this.ignoreOffer.delete(String(peerid));
            dataChannel.onmessage = this.handleMessage.bind(this, peerid);
            pc.onnegotiationneeded = this.negotiate.bind(this, this.contextid, pc, peerid);
            pc.onicecandidate = this.handleICECandidate.bind(this, this.contextid, peerid);
            pc.ontrack = this.handleTrackEvent.bind(this, peerid);
            pc.onconnectionstatechange = this.handleStateChange.bind(this, peerid);
            pc.oniceconnectionstatechange = () => {
                if (pc.iceConnectionState === "failed") {
                    Log.debug('restart');
                    pc.restartIce();
                }
            };
            this.peerConnections[String(peerid)] = pc;
        });
    }

    /**
     * Queue signal to peer
     *
     * @param {int} peerid Id of recipient
     * @param {string} type Signal type
     * @param {object} message Signal content
     */
    sendSignal(peerid, type, message) {
        this.queueout.push({
            message: JSON.stringify(message),
            peerid: peerid,
            type: type
        });
        this.sendSignals();
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

        const messages = [];
        while (this.queueout.length) {
            messages.push(this.queueout.shift());
        }

        Ajax.call([{
            args: {
                contextid: this.contextid,
                lastsignal: this.lastSignal,
                messages: messages
            },
            contextid: this.contextid,
            done: response => {
                Log.debug(response);
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
                            }).catch(Notification.exception);

                            // Close connections.
                            Object.values(this.peerConnections).forEach(pc => {
                                pc.close();
                            });

                            document.querySelectorAll(
                                '[data-region="deft-venue"] [data-peerid="' + this.peerid + '"]'
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
                });
                if (!response.peers.includes(Number(this.peerid))) {
                    return;
                }
                response.messages.forEach((signal) => {
                    if (signal.id > this.lastSignal) {
                        this.lastSignal = signal.id;
                        this.processSignal(signal);
                    }
                });

                for (const key in Object.keys(this.peerConnections)) {
                    if (!response.peers.includes(Number(key)) && this.peerConnections[key]) {
                        const pc = this.peerConnections[key];
                        pc.close();
                    }
                }
            },
            fail: Notification.exception,
            methodname: 'block_deft_send_signal'
        }]);
    }

    /**
     * Handle negotiation needed event
     *
     * @param {int} contextid Block conntextid
     * @param {RTCPeerConnection} pc Connection
     * @param {int} peerid Id of peer
     * @return {Promise}
     */
    negotiate(contextid, pc, peerid) {
        this.makingOffer.add(String(peerid));

        return pc.setLocalDescription().then(() => {
            return pc.setLocalDescription();
        }).then(() => {
            return this.sendSignal(peerid, 'audio-offer', pc.localDescription);
        }).catch(Log.debug).finally(() => {
            this.makingOffer.delete(String(peerid));
        });
    }

    /**
     * Process a signal
     *
     * @param {object} signal Signal received to process
     */
    processSignal(signal) {
        if ((signal.type === 'audio-offer') || (signal.type === 'audio-answer')) {
            const pc = this.peerConnections[String(signal.frompeer)] || new RTCPeerConnection({
                 iceServers: this.iceServers
            }),
                description = JSON.parse(signal.message),
                polite = (Number(signal.frompeer) < Number(this.peerid));
            if (!this.peerConnections[String(signal.frompeer)]) {
                this.peerConnections[String(signal.frompeer)] = pc;
                pc.onnegotiationneeded = this.negotiate.bind(this, this.contextid, pc, signal.frompeer);
                pc.oniceconnectionstatechange = () => {
                    if (pc.iceConnectionState === "failed") {
                        Log.debug('restart');
                        pc.restartIce();
                    }
                };
                pc.onicecandidate = this.handleICECandidate.bind(this, this.contextid, signal.frompeer);
                pc.ontrack = this.handleTrackEvent.bind(this, signal.frompeer);
                pc.onconnectionstatechange = this.handleStateChange.bind(this, signal.frompeer);
                pc.ondatachannel = (e) => {
                    this.peerAudioPlayer(signal.frompeer);
                    this.dataChannels.push(e.channel);
                    e.channel.onmessage = this.handleMessage.bind(this, signal.frompeer);
                    e.channel.onopen = () => {
                        window.setTimeout(() => {
                            e.channel.send(JSON.stringify({
                                "raisehand": !!document.querySelector(
                                    '[data-peerid="' + this.peerid + '"] a.hidden[data-action="raisehand"]'
                                )
                            }));
                        }, 3000);
                    };
                };
            }
            if (
                !polite
                && (description.type === 'offer')
                && (this.makingOffer.has(String(signal.frompeer)) || pc.signalingState !== "stable")
            ) {
                this.ignoreOffer.add(String(signal.frompeer));
                Log.debug('ignore offer');
                return;
            }
            this.ignoreOffer.delete(String(signal.frompeer));
            pc.setRemoteDescription(description).then(() => {
                Log.debug('Set Remote');
                return this.audioInput;
            }).then(audioStream => {
                    if (audioStream) {
                        Log.debug('audio stream');
                        if (pc.getTransceivers().length < 2) {
                            audioStream.getAudioTracks().forEach(track => {
                                pc.addTransceiver(track, {streams: [audioStream]});
                            });
                        }
                    }
                    Log.debug('Create answer');
                    if (description.type == 'offer') {
                        Log.debug('Set local');
                        return pc.setLocalDescription();
                    }
                    return audioStream;
            }).then(() => {
                if (description.type == 'offer') {
                    return this.sendSignal(signal.frompeer, 'audio-answer', pc.localDescription);
                }
                return pc;
            }).catch(Log.debug);
        } else if (signal.type === 'new-ice-candidate') {
            const pc = this.peerConnections[String(signal.frompeer)] || null;
            if (pc && pc.currentRemoteDescription) {
                pc.addIceCandidate(JSON.parse(signal.message)).catch(e => {
                    if (!this.ignoreOffer.has(String(signal.frompeer))) {
                        Log.debug(e);
                    }
                });
            }
        }
    }

    /**
     * Handle track event
     *
     * @param {int} peerid Id of peer
     * @param {event} e Track event
     */
    handleTrackEvent(peerid, e) {
        if (
            !e || !e.streams || !document.querySelector('#deft_audio')
        ) {
            return;
        }

        this.peerAudioPlayer(peerid).then((player) => {
            if (!player.srcObject) {
                player.srcObject = e.streams[0];
            }
            return;
        }).catch(Notification.exception);
    }

    /**
     * Change mute status
     *
     * @param {bool} state State to be set
     */
    mute(state) {
        this.audioInput.then(audioStream => {
            if (!audioStream) {
                return this.audioInput;
            }
            audioStream.getAudioTracks().forEach(track => {
                if (track.enabled == state) {
                    track.enabled = !state;
                }
            });
            return true;
        }).catch(Notification.exception);
    }

    /**
     * Raise or lower another peers hand
     *
     * @param {int} peerid Peer id
     * @param {event} e Message event
     */
    handleMessage(peerid, e) {
        const message = JSON.parse(e.data);
        if (message.hasOwnProperty('raisehand')) {
            Log.debug(peerid);
            Log.debug(message);
            document.querySelectorAll('[data-peerid="' + peerid + '"] [data-action="raisehand"]').forEach(button => {
                if (message.raisehand) {
                    button.classList.add('hidden');
                } else {
                    button.classList.remove('hidden');
                }
            });
            document.querySelectorAll('[data-peerid="' + peerid + '"] [data-action="lowerhand"]').forEach(button => {
                if (message.raisehand) {
                    button.classList.remove('hidden');
                } else {
                    button.classList.add('hidden');
                }
            });
        }
        if (message.hasOwnProperty('volume')) {
            document.querySelectorAll('.volume_indicator[data-peerid="' + peerid + '"]').forEach(indicator => {
                indicator.querySelector('.low').style.opacity = message.volume.low;
                indicator.querySelector('.mid').style.opacity = message.volume.mid;
                indicator.querySelector('.high').style.opacity = message.volume.high;
                indicator.setAttribute('data-volume', message.volume.smooth);
            });
        }
        //this.peerAudioPlayer(peerid);
    }

    /**
     * Adjust visiblity when state changes
     *
     * @param {int} peerid Peer id
     */
    handleStateChange(peerid) {
        const pc = this.peerConnections[String(peerid)];
        document.querySelectorAll('#deft_audio div[data-peerid="' + peerid + '"]').forEach(userinfo => {
            switch (pc.connectionState) {
                case 'connected':
                    userinfo.classList.remove('hidden');
                    break;
                case 'closed':
                    userinfo.remove();
                    break;
                case 'disconnected':
                    userinfo.classList.add('hidden');
                    break;
            }
        });
    }

    /**
     * Shut down gracefully before closing
     *
     * @param {Event} e Button click
     */
    closeConnections(e) {
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
        document.querySelector('body').classList.remove('block_deft_raisehand');
        Ajax.call([{
            args: {
                mute: false,
                "status": true
            },
            fail: Notification.exception,
            methodname: 'block_deft_venue_settings'
        }]);

        // Release microphone.
        clearInterval(this.meterId);
        this.audioInput.then(audioStream => {
            if (audioStream) {
                audioStream.getAudioTracks().forEach(track => {
                    track.stop();
                });
            }
            return true;
        }).catch(Notification.exception);

        // Close connections.
        Object.values(this.peerConnections).forEach(pc => {
            pc.close();
        });

        document.querySelectorAll('[data-region="deft-venue"] [data-peerid="' + this.peerid + '"]').forEach(venue => {
            const event = new Event('venueclosed');
            venue.dispatchEvent(event);
        });

        document.body.dispatchEvent(new CustomEvent('deftaction', { }));
        window.beforeunload = null;
        window.close();
        this.sockect.disconnect();
    }

    /**
     * Request audio device to share in venue
     */
    shareAudio() {
        this.audioInput = navigator.mediaDevices.getUserMedia({
            audio: {
                autoGainControl: this.autogaincontrol,
                echoCancellation: this.echocancellation,
                noiseSuppression: this.noisesuppression,
                sampleRate: this.samplerate
            },
            video: false
        }).then(audioStream => {

            Ajax.call([{
                args: {
                    mute: false,
                    "status": false
                },
                fail: Notification.exception,
                methodname: 'block_deft_venue_settings'
            }]);

            this.monitorVolume(audioStream);

            return audioStream;
        }).catch(Log.debug);
    }

    /**
     * Handle click for mute
     *
     * @param {Event} e Button click
     */
    handleMuteButtons(e) {
        const button = e.target.closest(
            'a[data-action="mute"], a[data-action="unmute"]'
        );
        Log.debug(e);
        if (button) {
            const action = button.getAttribute('data-action'),
                peerid = button.closest('[data-peerid]').getAttribute('data-peerid');
            e.stopPropagation();
            e.preventDefault();
            if (!button.closest('#deft_audio')) {
                this.audioInput.then(audioStream => {
                    if (audioStream) {
                        Ajax.call([{
                            args: {
                                mute: action == 'mute',
                                "status": false
                            },
                                fail: Notification.exception,
                            methodname: 'block_deft_venue_settings'
                        }]);
                    } else if (action == 'unmute') {
                        this.shareAudio();
                    }

                    return audioStream;
                }).catch(Notification.exception);
            } else {
                Ajax.call([{
                    args: {
                        mute: true,
                        peerid: peerid,
                        "status": false
                    },
                    fail: Notification.exception,
                    methodname: 'block_deft_venue_settings'
                }]);
            }
            button.closest('[data-peerid]').querySelectorAll('[data-action="mute"], [data-action="unmute"]').forEach(option => {
                if (option.getAttribute('data-action') == action) {
                    option.classList.add('hidden');
                } else {
                    option.classList.remove('hidden');
                }
            });
            document.body.dispatchEvent(new CustomEvent('deftaction', { }));
        }
    }

    /**
     * Handle hand raise buttons
     *
     * @param {Event} e Click event
     */
    handleRaiseHand(e) {
        const button = e.target.closest(
            '[data-action="raisehand"], [data-action="lowerhand"]'
        );
        if (button && !button.closest('#deft_audio')) {
            const action = button.getAttribute('data-action');
            e.stopPropagation();
            e.preventDefault();
            if (action == 'raisehand') {
                document.querySelector('body').classList.add('block_deft_raisehand');
            } else {
                document.querySelector('body').classList.remove('block_deft_raisehand');
            }
            document.querySelectorAll('a[data-action="raisehand"], a[data-action="lowerhand"]').forEach(button => {
                if (button.getAttribute('data-action') == action) {
                    button.classList.add('hidden');
                } else {
                    button.classList.remove('hidden');
                }
            });
            Ajax.call([{
                args: {
                    "status": action == 'raisehand'
                },
                    fail: Notification.exception,
                methodname: 'block_deft_raise_hand'
            }]);
            this.sendMessage(JSON.stringify({"raisehand": action == 'raisehand'}));
        }
    }

    /**
     * Send a message through data channel to peers
     *
     * @param {string} message
     */
    sendMessage(message) {
        this.dataChannels.forEach(dataChannel => {
            if (dataChannel.readyState == 'open') {
                dataChannel.send(message);
            }
        });
    }

    /**
     * Process audio to provide visual feedback
     *
     * @param {MediaStream} audioStream Audio from user's microphone
     * @returns {MediaStream}
     */
    monitorVolume(audioStream) {
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
                document.querySelectorAll('.volume_indicator[data-peerid="' + this.peerid + '"]').forEach(indicator => {
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
                document.querySelectorAll('#deft_audio > div').forEach(peer => {
                    peers.push(peer);
                });
                peers.sort((a, b) => {
                    let volume = 0;
                    a.querySelectorAll('[data-volume]').forEach(indicator => {
                        volume += -Number(indicator.getAttribute('data-volume'));
                    });
                    b.querySelectorAll('[data-volume]').forEach(indicator => {
                        volume += Number(indicator.getAttribute('data-volume'));
                    });
                    return volume;
                });
                peers.forEach(peer => {
                    document.querySelector('#deft_audio').appendChild(peer);
                });
            }, 500);
        }

        return audioStream;
    }

    /**
     * Return audio player for peer
     *
     * @param {int} peerid Peer id
     * @returns {Promise} Resolve to audio player node
     */
    peerAudioPlayer(peerid) {
        const usernode = document.querySelector('#deft_audio div[data-peerid="' + peerid + '"] audio');
        if (usernode) {
            return Promise.resolve(usernode);
        } else {
            const node = document.createElement('div');
            node.setAttribute('data-peerid', peerid);
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
                    peerid: peerid
                }
            ).done((userinfo) => {
                if (!document.querySelector('#deft_audio div[data-peerid="' + peerid + '"] audio')) {
                    document.querySelector('#deft_audio').appendChild(node);
                    node.innerHTML = userinfo;
                }
            }).then(() => {
                return document.querySelector('#deft_audio div[data-peerid="' + peerid + '"] audio');
            }).catch(Notification.exception);
        }
    }
}
