/*
 * Refresh content when changed on the system
 *
 * @package    block_deft
 * @module     block_deft/jitis/refresh
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from "core/fragment";
import JitsiMeetJS from "block_deft/jitsi/lib-jitsi-meet.min";
import Log from "core/log";
import Socket from "block_deft/jitsi/socket";
import Templates from "core/templates";

var domain;

export default class {

    /**
     * Listen to WebSocket and refresh content
     *
     * @param {int} contextid Context id of block
     * @param {string} selector Content location to replace
     * @param {string} jwt Authentication token to connect server
     * @param {int} throttle Throttle dely in ms
     * @param {string} server Jitsi server to use
     * @param {string} room Room name
     */
    constructor(contextid, selector, jwt, throttle, server, room) {
        this.contextid = contextid;
        this.selector = selector;
        this.throttle = throttle;
        this.throttled = false;
        this.lastupdate = 0;
        this.server = server;
        domain = server;

        JitsiMeetJS.init();
        JitsiMeetJS.setLogLevel(JitsiMeetJS.logLevels.DEBUG);

        this.connection = new JitsiMeetJS.JitsiConnection(null, jwt, {
            serviceUrl: `https://${ domain }/http-bind`,
            hosts: {
                domain: domain,
                muc: `conference.${ domain }`
            }
        });
        this.connection.addEventListener(JitsiMeetJS.events.connection.CONNECTION_ESTABLISHED, () => {
            this.room = this.connection.initJitsiConference(room, {
                disableSimulcast: true
            });
            this.room.addCommandListener('updateinterface', e => {
                this.handleMessage(e.attributes.id, {
                    data: e.attributes.message
                });
            });

            this.socket = new Socket(this.room);
            this.socket.subscribe(() => {
                this.update();
            });
            document.body.addEventListener('deftaction', () => {
                this.socket.notify();
            });

            this.room.join();
        });

        this.connection.connect();
    }

    /**
     * Refresh content
     *
     */
    update() {
        if (document.querySelector(this.selector)) {
            let content = document.querySelector(this.selector).parentNode,
                component = content.closest('[data-component]')
                    && content.closest('[data-component]').getAttribute('data-component')
                    || 'block_deft',
                data = {};
            if (!content) {
                return;
            }

            if (
                (this.lastupdate + this.throttle > Date.now())
                || (document.activeElement.closest(this.selector) && document.activeElement.closest('select'))
            ) {
                if (
                    !this.throttled
                    || (this.lastupdate + this.throttle < Date.now())
                ) {
                    setTimeout(() => {
                        this.update();
                    }, Math.max(this.lastupdate + this.throttle - Date.now(), 40));
                    this.throttled = true;
                }

                return;
            }

            document.querySelector(this.selector)
                .querySelectorAll('[data-type="comments"] .block_deft_comments.expanded')
                .forEach((opencomments) => {
                    data.opencomments = data.opencomments || [];
                    data.opencomments.push(opencomments.closest('[data-task]').getAttribute('data-task'));
                });

            if (document.querySelector(this.selector).closest('[data-modified]')) {
                data.lastmodified = document.querySelector(this.selector).closest('[data-modified]').getAttribute('data-modified');
            }

            Fragment.loadFragment(
                component,
                'content',
                this.contextid,
                {
                    jsondata: JSON.stringify(data)
                }
            ).done((html, js) => {
                if (html) {
                    this.replace(content, html, js);
                }
            }).catch(Log.debug);

            this.throttled = false;
            this.lastupdate = Date.now();
        }
    }

    /**
     * Replace content
     *
     * @param {DOMNode} content
     * @param {string} html New content
     * @param {string} js Scripts to run after replacement
     */
    replace(content, html, js) {
        let setScroll = () => {
                return true;
            },
            setHeight = () => {
                return true;
            };
        content.style.height = content.offsetHeight;
        setTimeout(() => {
            content.style.height = null;
        });
        content.querySelectorAll('.block_deft_comments').forEach((comments) => {
            const position = comments.scrollTop,
                task = comments.closest('[data-task]').getAttribute('data-task'),
                recurse = setScroll;
            setScroll = () => {
                content.querySelectorAll('[data-task="' + task + '"]').forEach((task) => {
                    task.querySelector('.block_deft_comments').scrollTop = position;
                });
                recurse();
            };
        });

        content.querySelectorAll('[data-summary]').forEach((summary) => {
            const height = summary.offsetHeight,
                task = summary.getAttribute('data-summary'),
                recurse = setHeight;
            setHeight = () => {
                content.querySelectorAll('[data-summary="' + task + '"]').forEach((summary) => {
                    summary.setAttribute('style', 'min-height: ' + height + 'px;');
                });
                recurse();
            };
        });
        Templates.replaceNodeContents(content, html, js);
        setScroll();
        setHeight();
    }
}
