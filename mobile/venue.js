var Janus;
// eslint-disable-next-line no-unused-vars
const DeftVenue = {
    /**
     * Attach audio bridge plugin
     */
    attachAudioBridge() {
        this.janus.attach({
            plugin: 'janus.plugin.audiobridge',
            opaqueId: 'audioroom-' + Janus.randomString(12),
            success: pluginHandle => {
                this.audioBridge = pluginHandle;
                this.register(pluginHandle);
            },
            error: function(error) {
                Janus.debug('audiobridge: ' + error);
            },
            onmessage: (msg, jsep) => {
                const event = msg.audiobridge;
                if (event) {
                    if (event === 'joined') {
                        // Successfully joined, negotiate WebRTC now
                        if (msg.id) {
                            this.updateParticipants();
                            if (!this.webrtcUp) {
                                this.webrtcUp = true;
                                    const tracks = [];
                                        tracks.push({
                                            type: 'audio',
                                            recv: true
                                        });
                                navigator.mediaDevices.getUserMedia({
                                    audio: true,
                                    video: false
                                }).catch((e) => {
                                    Janus.warn(e);
                                    document.querySelectorAll(
                                        '[data-action="unmute"]'
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
                                            this.audioTrack = track;
                                            track.enabled = false;
                                            this.monitorVolume(audioStream);
                                        });
                                    }
                                    this.audioBridge.createOffer({
                                        // We only want bidirectional audio
                                        tracks: tracks,
                                        success: (jsep) => {
                                            Janus.debug('Got SDP!', jsep);
                                            const publish = {request: 'configure', muted: false};
                                            this.audioBridge.send({message: publish, jsep: jsep});
                                        },
                                        error: function(error) {
                                            Janus.error('WebRTC error... ', error.message);
                                        }
                                    });

                                    return audioStream;
                                }).catch(Janus.warn);
                            }
                        }
                    } else if (event === 'destroyed') {
                        // The room has been destroyed
                        Janus.warn('The room has been destroyed!');
                    } else if (event === 'event') {
                        if (msg.participants) {
                            this.updateParticipants();
                        } else if (msg.error) {
                            if (msg.error_code === 485) {
                                // This is a 'no such room' error: give a more meaningful description
                                Janus.error(
                                    'Error:',
                                    '<p>Room <code>' + this.roomid + '</code> is not configured.'
                                );
                            } else {
                                Janus.error(msg.error_code, msg.error);
                            }
                            return;
                        }
                        if (msg.leaving) {
                            // One of the participants has gone away?
                            const leaving = msg.leaving;
                            Janus.log(
                                'Participant left: ' + leaving
                            );
                            document.querySelectorAll(
                                '#deft_audio [peerid="' + leaving + '"]'
                            ).forEach(peer => {
                                peer.remove();
                            });
                        }
                    }
                }
                if (jsep) {
                    Janus.debug('Handling SDP as well...', jsep);
                    this.audioBridge.handleRemoteJsep({jsep: jsep});
                }
            },
            onremotetrack: (track, mid, on, metadata) => {
                Janus.debug(
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
                Janus.attachMediaStream(document.getElementById('roomaudio'), this.remoteStream);
            }
        });
    },

    /**
     * Attach text room plugin
     */
    attachTextRoom() {
        this.janus.attach({
            plugin: 'janus.plugin.textroom',
            opaqueId: 'textroom-' + Janus.randomString(12),
            success: pluginHandle => {
                this.textroom = pluginHandle;
                Janus.log('Plugin attached! (' + this.textroom.getPlugin()
                    + ', id=' + this.textroom.getId() + ')');
                // Setup the DataChannel
                const body = {request: 'setup'};
                Janus.debug('Sending message:', body);
                this.textroom.send({message: body});
            },
            error: function(error) {
                Janus.debug('  -- Error attaching plugin...', error);
            },
            onmessage: (msg, jsep) => {
                Janus.debug(' ::: Got a message :::', msg);
                if (msg.error) {
                    Janus.error(msg.error_code, msg.error);
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
                        room: this.roomid,
                        username: String(this.peerid),
                        display: '',
                    };
                this.textroom.data({
                    text: JSON.stringify(register),
                    error: function(reason) {
                        Janus.error('Error ', reason);
                    }
                });
            },
            ondata: (data) => {
                this.textroom.dataUp = true;
                Janus.debug('We got data from the DataChannel!', data);
                const message = JSON.parse(data),
                    event = message.textroom;

                if (event === 'message') {
                    const data = JSON.parse(message.text);
                    if (data.hasOwnProperty('raisehand')) {
                        document.querySelectorAll(
                            '[data-peerid="' + message.from + '"] [data-role="raisehand"]'
                        ).forEach(button => {
                            button.style.display = data.raisehand ? 'inline' : 'none';
                        });
                    }
                    document.getElementById('guests').innerHTML = document.querySelectorAll('#participants > ion-item').length;
                    document.querySelectorAll('#hands').forEach(handcount => {
                        let count = 0;
                        document.querySelectorAll('#participants ion-icon[data-role="raisehand"]').forEach(icon => {
                            if (icon.style.display === 'inline') {
                                count++;
                            }
                        });
                        handcount.innerHTML = count;
                        handcount.parentNode.style.opacity = count ? 1 : 0;
                    });
                    if (data.hasOwnProperty('volume')) {
                        document.querySelectorAll(
                            '#participants ion-item[data-peerid="' + message.from + '"]'
                        ).forEach(indicator => {
                            indicator.querySelector('.indicator').style.opacity
                                = (data.volume.high + data.volume.mid + data.volume.high) / 3;
                            indicator.setAttribute('data-volume', data.volume.smooth);
                        });
                    }
                }
                if (event === 'error') {
                    Janus.error('data: ', message.error);
                }
                if (event === 'join') {
                    this.sendMessage(JSON.stringify({
                        'raisehand': this.raisehand
                    }));
                }
            }
        });
    },

    /**
     * Add event listeners for ui
     */
    addListeners: function() {
        document.removeEventListener('click', e => {
            this.handleClick(e);
        });
        document.addEventListener('click', e => {
            this.handleClick(e);
        });
    },

    /**
     * Handle click event
     *
     * @param {Event} e Click event
     */
    handleClick: function(e) {
        const action = e.target.getAttribute('data-action');
        if (action == 'pause' || action == 'start') {
            this.pauseDisplay(action == 'pause');
        }
    },

    /**
     * Pause or start video display stream
     *
     * @param {bool} state to pause or not to pause
     */
    pauseDisplay: function(state) {
        this.paused = state;
        document.querySelectorAll('[data-action="pause"], [data-action="start"]').forEach(button => {
            button.style.display = ((button.getAttribute('data-action') === 'start') === state) ? null : 'none';
        });
        if (this.videoroom && this.currentFeed) {
            const request = {
                request: state ? 'pause' : 'start'
            };
            this.videoroom.send({message: request});
        } else {
            this.subscribeTo(this.resumeFeed);
        }
    },

    /**
     * Change hand signal
     *
     * @param {bool} state Whether to raise hand
     */
    raiseHand: function(state) {
        this.raisehand = state;
        document.querySelectorAll(
            '[data-action="lowerhand"], [data-action="raisehand"]'
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

    /**
     * Change mute state
     *
     * @param {bool} state Whether to mute microphone
     */
    switchMute: function(state) {
        if (!this.audioTrack) {
            return;
        }
        this.audioTrack.enabled = !state;
        document.querySelectorAll(
            '[data-action="mute"], [data-action="unmute"]'
        ).forEach(function(button) {
            if (state == (button.getAttribute('data-action') == 'unmute')) {
                button.style.display = null;
            } else {
                button.style.display = 'none';
            }
        });
    },

    /**
     * Send message to other peers
     *
     * @param {string} text message
     */
    sendMessage: function(text) {
        if (text && text !== '' && this.textroom && this.textroom.dataUp) {
            const message = {
                textroom: 'message',
                room: this.roomid,
                transaction: Janus.randomString(12),
                text: text
            };
            this.textroom.data({
                text: JSON.stringify(message),
                error: reason => {
                    Janus.error('Error: ', reason);
                }
            });
        }
    },

    /**
     * Update the the participants list in ui
     */
    updateParticipants: function() {
        document.querySelectorAll('[data-action="pause"], [data-action="start"]').forEach(button => {
            button.style.display = ((button.getAttribute('data-action') === 'start') === !!this.paused) ? null : 'none';
        });
        this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id).then(site => {
            return site.read('block_deft_get_participants', {
                taskid: this.taskid
            }, {getFromCache: false, saveToCache: false, reusePending: false});
        }).then(response => {
            response.participants.forEach(participant => {
                if (participant.id == this.peerid) {
                   this.switchMute(participant.mute);
                }
                if (participant.status) {
                    document.querySelectorAll(
                        '#participants [data-peerid="' + participant.id + '"]'
                    ).forEach(item => {
                        item.remove();
                    });
                } else {
                    if (!document.querySelector('#participants [data-peerid="' + participant.id + '"]')) {
                        const item = document.createElement('ion-item');
                        item.setAttribute('data-peerid', participant.id);
                        item.innerHTML = participant.content;
                        document.getElementById('participants').appendChild(item);
                    }
                }
                document.querySelectorAll(
                    '[data-peerid="' + participant.id + '"] .indicator'
                ).forEach(button => {
                    button.style.display = !participant.mute ? 'inline' : 'none';
                });
                document.querySelectorAll(
                    '[data-peerid="' + participant.id + '"] [data-role="mute"]'
                ).forEach(button => {
                    button.style.display = participant.mute ? 'inline' : 'none';
                });
            });

            document.querySelectorAll('#participants ion-item[data-peerid="' + this.peerid + '"] ion-label').forEach(label => {
                label.color = 'info';
            });

            this.subscribeTo(Number(response.feed));
            return response;
        }).catch(Janus.warn);
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

    /**
     * Register plugin when attached
     *
     * @param {object} pluginHandle Janus plugin handl
     * @returns {Promise}
     */
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

        return this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id).then(site => {
            return site.read('block_deft_join_room', args, {getFromCache: false, saveToCache: false, reusePending: false});
        }).catch(Janus.warn);
    },

    /**
     * Subscribe to video feed
     *
     * @param {int} source Peer id of source feed
     */
    subscribeTo: function(source) {
        if (!this.janus || !this.audioBridge || this.creatingSubscription || source === this.currentFeed) {
            return;
        }
        this.resumeFeed = source;

        if (this.remoteVideoStream) {
            this.videoroom.detach();
            this.videoroom = null;
            this.remoteVideoStreams = {};
            this.remoteVideoStream = null;
            this.creatingSubscription = false;
        }

        if (!source) {
            document.getElementById('roomvideo').style.display = 'none';
            return;
        }

        if (this.paused) {
            return;
        }

        this.creatingSubscription = true;

        this.currentFeed = source;

        this.janus.attach({
            plugin: 'janus.plugin.videoroom',
            opaqueId: 'videoroom-' + Janus.randomString(12),
            success: pluginHandle => {
                this.videoroom = pluginHandle;
                this.register(pluginHandle);
            },
            error: (error) => {
                Janus.error('videoroom: ', error);
            },
            onmessage: this.onMessage.bind(this),
            onremotetrack: (track, mid, on, metadata) => {
                Janus.debug(
                    'Remote track (mid=' + mid + ') ' +
                    (on ? 'added' : 'removed') +
                    (metadata ? ' (' + metadata.reason + ') ' : '') + ':', track
                );
                if (!on) {
                    // Track removed, get rid of the stream and the rendering
                    delete this.remoteVideoStreams[mid];
                    return;
                }
                this.remoteVideoStreams = this.remoteVideoStreams || {};
                if (!this.remoteVideoStreams.hasOwnProperty(mid) && track.kind === 'video') {
                    this.remoteVideoStreams[mid] = track;
                    if (this.remoteVideoStream) {
                        return;
                    }
                    this.remoteVideoStream = new MediaStream([track]);
                    this.remoteVideoStream.mid = mid;
                    this.attachVideo(this.remoteVideoStream);
                }
            }
        });
    },

    /**
     * Mutation observer to handle leaving page
     *
     * @param {array} records
     * @param {Observer} observer
     */
    observe: function(records, observer) {
        if (!this.janus || document.querySelector('audio')) {
            return;
        }

        this.janus.destroy();

        this.janus = null;
        this.textroom = null;

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
            return site.read('block_deft_venue_settings', {
                mute: true,
                peerid: this.peerid,
                status: true,
                uuid: this.Device.uuid
            });
        }).catch(Janus.warn);
    },

    /**
     * Handle message from video room plugin
     *
     * @param {object} msg message
     * @param {jsep} jsep
     */
    onMessage: function(msg, jsep) {
        const event = msg.videoroom,
            pluginHandle = this.videoroom;
        Janus.debug(' ::: Got a message :::', msg);
        Janus.debug('Event: ' + event);
        switch (event) {
            case 'destroyed':
                // The room has been destroyed
                Janus.warn('The room has been destroyed!');
                break;
            case 'attached':
                this.creatingSubscription = false;
                this.updateParticipants();
                break;
            case 'event':
                if (msg.error) {
                    if (msg.error_code === 485) {
                        // This is a 'no such room' error: give a more meaningful description
                        // eslint-disable-next-line no-alert
                        alert(
                            '<p>Apparently room <code>' + this.roomid + '</code> is not configured</p>'
                        );
                    } else if (msg.error_code === 428) {
                        // eslint-disable-next-line no-console
                        console.log(msg.error);
                    } else {
                        // eslint-disable-next-line no-alert
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
                    }
                }
            );
        }
    },

    /**
     * Attach video stream to ui
     *
     * @param {MediaStream} videoStream
     */
    attachVideo: function(videoStream) {
        document.getElementById('roomvideo').style.display = 'block';
        Janus.attachMediaStream(
            document.getElementById('roomvideo'),
            videoStream
        );
    },

    CoreSitesProvider: this.CoreSitesProvider,

    Device: this.Device
};
