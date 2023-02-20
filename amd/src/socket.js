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


const Socket = class {
    /**
     * Listen to WebSocket and refresh content
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token to connect service
     */
    constructor(contextid, token) {
        this.listeners = [];
        this.connect(contextid, token);
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
                this.websocket.addEventListener('message', callback);
                callback(e);
            });
        };

        this.websocket.addEventListener('close', (e) => {
            Log.debug('Disconnected');
            if (this.disconnected) {
                return this;
            } else if (e.code == 1011) {
                Log.debug('Authentication failed');
                Ajax.call([{
                    methodname: 'block_deft_renew_token',
                    args: {contextid: contextid},
                    done: (replacement) => {
                        Log.debug('Reconnecting');
                        this.connect(contextid, replacement.token);
                    },
                    fail: Notification.exception
                }]);
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
        this.websocket.close();

        return this;
    }

    /**
     * Subscribe listener
     *
     * @param {function} callback
     * @returns {object}
     * @chainable
     */
    subscribe(callback) {
        this.websocket.addEventListener('message', callback);
        this.listeners.push(callback);

        return this;
    }
};

export default Socket;
