/*
 * Open and maintain a WebSocket to recieve messages from server.
 *
 * @package    block_deft
 * @module     block_deft/socket
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Log from "core/log";
import Notification from "core/notification";

export class Socket {
    /**
     * Listen for messages and refresh content
     *
     * @param {JitsiConferenceRoom} room Jitsi room
     */
    constructor(room) {
        this.room = room;
        this.listeners = [];
    }

    /**
     * Connect to service
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token to connect service
     * @returns {object}
     * @chainable
     */
    connect(contextid, token) {
        this.websocket = new WebSocket('wss://deftly.us/ws');
        this.websocket.onopen = (e) => {
            this.websocket.send(token);
            this.listeners.forEach((callback) => {
                this.websocket.addCommandListener('message', callback);
                callback.apply(this, [e]);
            });
        };

        this.websocket.addEventListener('close', (e) => {
            Log.debug('Disconnected');
            if (this.disconnected) {
                return this;
            } else if (e.code == 1011) {
                Log.debug('Authentication failed');
                this.renewToken(contextid);
            } else {
                setTimeout(() => {
                    Log.debug('Reconnecting');
                    this.connect(contextid, token);
                }, 5000);
            }

            return true;
        });

        return this;
    }

    /**
     * Disconnect socket
     *
     * @returns {object}
     * @chainable
     */
    disconnect() {
        this.disconnected = true;
        //this.websocket.close();

        return this;
    }

    notify() {
        this.room.sendCommandOnce('message', {});
    }

    /**
     * Subscribe listener
     *
     * @param {function} callback
     * @returns {object}
     * @chainable
     */
    subscribe(callback) {
        this.room.addCommandListener('message', callback);
        this.listeners.push(callback);

        return this;
    }

    /**
     * Renew token
     *
     * @param {int} contextid Context id of block
     */
    renewToken(contextid) {
        Ajax.call([{
            methodname: 'block_deft_renew_token',
            args: {contextid: contextid},
            done: (replacement) => {
                Log.debug('Reconnecting');
                this.connect(contextid, replacement.token);
            },
            fail: Notification.exception
        }]);
    }
}

export default Socket;
