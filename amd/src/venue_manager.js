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
import Notification from "core/notification";
import Log from "core/log";
import Socket from "block_deft/socket";

export default {

    lastSignal: 0,

    lastUpdate: 0,

    dataChannels: [],

    peerConnections: [],

    queue: [],

    queueout: [],

    /**
     * Listen for comment actions
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token
     * @param {array} peers
     * @param {int} peerid My peer id
     * @param {array} iceServers ICE server array to configure peers
     */
    init: function(contextid, token, peers, peerid, iceServers) {
        this.contextid = contextid;
        this.peerid = peerid;
        this.iceServers = iceServers;

        this.audioInput = navigator.mediaDevices.getUserMedia({
            audio: {
                autoGainControl: true,
                echoCancellation: true,
                noiseSuppression: true,
                sampleRate: 8000
            },
            video: false
        });
        document.querySelector('body').addEventListener('click', e => {
            const button = e.target.closest('a[data-action="mute"], a[data-action="unmute"]');
            if (button) {
                const action = button.getAttribute('data-action');
                    peerid = button.closest('[data-peerid]').getAttribute('data-peerid');
                e.stopPropagation();
                e.preventDefault();
                if (peerid == this.peerid) {
                    this.mute(action == 'mute');
                    Ajax.call([{
                        args: {
                            mute: action == 'mute',
                            "status": false
                        },
                            fail: Notification.exception,
                        methodname: 'block_deft_venue_settings'
                    }]);
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
            }
        });
        peers.forEach(peerid => {
            const pc = new RTCPeerConnection({
                 iceServers: iceServers
            }),
                dataChannel = pc.createDataChannel('Events');
            this.dataChannels.push(dataChannel);
            dataChannel.onmessage = this.handleMessage.bind(this, peerid);
            pc.onnegotiationneeded = this.negotiate.bind(this, contextid, pc, peerid);
            pc.onicecandidate = this.handleICECandidate.bind(this, contextid, peerid);
            pc.ontrack = this.handleTrackEvent.bind(this, peerid);
            pc.onconnectionstatechange = this.handleStateChange.bind(this, peerid);
            this.peerConnections[peerid] = pc;
        });

        document.querySelectorAll('a[data-action="raisehand"], a[data-action="lowerhand"]').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = button.getAttribute('data-action');
                e.stopPropagation();
                e.preventDefault();
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
                this.dataChannels.forEach(dataChannel => {
                    if (dataChannel.readyState != 'open') {
                        return;
                    }
                    if (action == 'raisehand') {
                        dataChannel.send('{"raisehand": true}');
                    } else {
                        dataChannel.send('{"raisehand": false}');
                    }
                });
            });
        });

        document.querySelectorAll('a[data-action="close"]').forEach(button => {
            button.addEventListener('click', e => {
                e.stopPropagation();
                e.preventDefault();
                button.firstChild.classList.add('bg-warning');
                this.closeConnections();
            });
        });
        window.onbeforeunload = this.closeConnections.bind(this);

        let socket = new Socket(contextid, token);
        socket.subscribe(() => {
            this.sendSignals();
        });
    },

    /**
     * Handle ICE candidate event
     *
     * @param {int} contextid Block context id
     * @param {int} peerid Recipient id
     * @param {event} e ICE candidate event
     */
    handleICECandidate: function(contextid, peerid, e) {
        if (e.candidate) {
            this.sendSignal(peerid, 'new-ice-candidate', e.candidate);
        }
    },

    /**
     * Queue signal to peer
     *
     * @param {int} peerid Id of recipient
     * @param {string} type Signal type
     * @param {object} message Signal content
     */
    sendSignal: function(peerid, type, message) {
        this.queueout.push({
            message: JSON.stringify(message),
            peerid: peerid,
            type: type
        });
        this.sendSignals();
    },

    /**
     * Transfer signals with signal server
     */
    sendSignals: function() {

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
                response.settings.forEach(peer => {
                    if (peer.id == Number(this.peerid)) {
                        if (peer.status) {
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
                response.messages.forEach((signal) => {
                    if (signal.id > this.lastSignal) {
                        this.lastSignal = signal.id;
                        this.processSignal(signal);
                    }
                });

                for (const key in this.peerConnections.keys()) {
                    if (!response.peers.includes(key)) {
                        const pc = this.peerConnections[key];
                        Log.debug('Close ' + key);
                        pc.close();
                        this.peerConnections[key] = null;
                    }
                }
            },
            fail: Notification.exception,
            methodname: 'block_deft_send_signal'
        }]);
    },

    /**
     * Handle negtiation needed event
     *
     * @param {int} contextid Block conntextid
     * @param {RTCPeerConnection} pc Connection
     * @param {int} peerid Id of peer
     * @return {Promise}
     */
    negotiate: function(contextid, pc, peerid) {
        return pc.createOffer().then(offer => {
            return pc.setLocalDescription(offer).then(() => {
                return this.sendSignal(peerid, 'audio-offer', offer);
            }).catch(Log.debug);
        });
    },

    /**
     * Recursively process queue
     *
     * @param {object} signal Signal received to process
     */
    processSignal: function(signal) {
        if (signal.type === 'audio-offer') {
            const pc = this.peerConnections[signal.frompeer] || new RTCPeerConnection({
                 iceServers: this.iceServers
            });
            if (!this.peerConnections[signal.frompeer]) {
                this.peerConnections[signal.frompeer] = pc;
            }
            Log.debug('Received offer');
            pc.onnegotiationneeded = this.negotiate.bind(this, this.contextid, pc, signal.frompeer);
            pc.onicecandidate = this.handleICECandidate.bind(this, this.contextid, signal.frompeer);
            pc.ontrack = this.handleTrackEvent.bind(this, signal.frompeer);
            pc.onconnectionstatechange = this.handleStateChange.bind(this, signal.frompeer);
            pc.ondatachannel = (e) => {
                this.dataChannels.push(e.channel);
                e.channel.onmessage = this.handleMessage.bind(this, signal.frompeer);
                e.channel.onopen = () => {
                    if (document.querySelector('[data-peerid="' + this.peerid + '"] a.hidden[data-action="raisehand"]')) {
                        window.setTimeout(() => {
                            e.channel.send('{"raisehand": true}');
                        }, 3000);
                    }
                };
            };
            pc.setRemoteDescription(JSON.parse(signal.message)).then(() => {
                Log.debug('Set Remote');
                return this.audioInput.then(audioStream => {
                    Log.debug('audio stream');
                    if (pc.getTransceivers().length < 2) {
                        audioStream.getAudioTracks().forEach(track => {
                            pc.addTransceiver(track, {streams: [audioStream]});
                        });
                    }
                    Log.debug('Create answer');
                    return pc.createAnswer().then(answer => {
                        Log.debug('Answer created');
                        if (!pc || pc.signalingState == 'stable') {
                            return false;
                        }
                        return pc.setLocalDescription(answer).then(() => {
                            Log.debug('Set local');
                            return this.sendSignal(signal.frompeer, 'audio-answer', answer);
                        }).catch(Log.debug);
                    }).catch(Notification.exception);
                }).catch(Notification.exception);
            }).catch(Log.debug);
        } else if (signal.type === 'audio-answer') {
            const pc = this.peerConnections[signal.frompeer];
            Log.debug('Audio answer');
            if (pc && pc.signalingState == 'have-local-offer') {
                pc.setRemoteDescription(JSON.parse(signal.message));
            }
        } else if (signal.type === 'new-ice-candidate') {
            const pc = this.peerConnections[signal.frompeer] || null;
            if (pc && pc.currentRemoteDescription) {
                pc.addIceCandidate(JSON.parse(signal.message));
            }
        }
    },

    /**
     * Handle track event
     *
     * @param {int} peerid Id of peer
     * @param {event} e Track event
     */
    handleTrackEvent: function(peerid, e) {
        if (!e || !e.streams || document.querySelector('#deft_audio div[data-peerid="' + peerid + '"]')) {
            return;
        }
        Log.debug('Track');
        Log.debug(e);

        const node = document.createElement('div');
        node.setAttribute('data-peerid', peerid);
        node.setAttribute('class', 'col col-12 col-md-6 col-lg-4 col-xl-3 m-2');
        window.setTimeout(() => {
            node.querySelectorAll('img.card-img-top').forEach(image => {
                image.setAttribute('height', null);
                image.setAttribute('width', null);
            });
        });
        document.querySelector('#deft_audio').appendChild(node);
        Fragment.loadFragment(
            'block_deft',
            'venue',
            this.contextid,
            {
                peerid: peerid
            }
        ).done((userinfo) => {
            node.innerHTML = userinfo;
            const player = node.querySelector('audio');
            player.srcObject = e.streams[0];
        }).catch(Notification.exception);
    },

    /**
     * Change mute status
     *
     * @param {bool} state State to be set
     */
    mute: function(state) {
        this.audioInput.then(audioStream => {
            audioStream.getAudioTracks().forEach(track => {
                if (track.enabled == state) {
                    track.enabled = !state;
                }
            });
            return true;
        }).catch(Notification.exception);
    },

    /**
     * Raise or lower peers hand
     *
     * @param {int} peerid Peer id
     * @param {event} e Message event
     */
    handleMessage: function(peerid, e) {
        const message = JSON.parse(e.data);
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
    },

    /**
     * Adjust visiblity when state changes
     *
     * @param {int} peerid Peer id
     */
    handleStateChange(peerid) {
        const pc = this.peerConnections[peerid];
        document.querySelectorAll('#deft_audio div[data-peerid="' + peerid + '"]').forEach(userinfo => {
            switch (pc.connectionState) {
                case 'connected':
                    userinfo.classList.remove('hidden');
                    break;
                case 'close':
                case 'failed':
                    userinfo.remove();
                    break;
                case 'disconnected':
                    userinfo.classList.add('hidden');
                    break;
            }
        });
    },

    /**
     * Shut down gracefully before closing
     */
    closeConnections() {
        Ajax.call([{
            args: {
                mute: false,
                "status": true
            },
            fail: Notification.exception,
            methodname: 'block_deft_venue_settings'
        }]);
        this.peerConnections.forEach(pc => {
            pc.close();
        });

        // Release microphone.
        this.audioInput.then(audioStream => {
            audioStream.getAudioTracks().forEach(track => {
               track.stop();
            });
            return true;
        }).catch(Notification.exception);

        window.beforeunload = null;
    }
};
