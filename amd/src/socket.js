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

var websocket = new WebSocket('wss://deftly.us/ws'),
    listeners = [];

export default {
    /**
     * Listen to WebSocket and refresh content
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token to connect service
     * @returns {object}
     * @chainable
     */
    open: function(contextid, token) {
        websocket.onopen = (e) => {
            websocket.send(token);
            listeners.forEach((callback) => {
                callback(e);
            });
        };

        websocket.addEventListener('close', (e) => {
            Log.debug('Disconnected');
            if (e.code == 1011) {
                Log.debug('Authentication failed');
                Ajax.call([{
                    methodname: 'block_deft_renew_token',
                    args: {contextid: contextid},
                    done: (replacement) => {
                        this.reconnect(contextid, replacement.token);
                    },
                    fail: Notification.exception
                }]);
            } else {
                setTimeout(() => {
                    this.reconnect(contextid, token);
                }, 5000);
            }
        });

        return this;
    },

    /**
     * Attempt reconnecting to service
     *
     * @param {int} contextid Context id of block
     * @param {string} token Authentication token to connect service
     */
    reconnect: function(contextid, token) {
        Log.debug('Reconnecting');
        websocket = new WebSocket('wss://deftly.us/ws');
        this.open(contextid, token);
        listeners.forEach((callback) => {
            websocket.addEventListener('message', callback);
        });
    },

    /**
     * Subscribe listener
     *
     * @param {function} callback
     * @returns {object}
     * @chainable
     */
    subscribe: function(callback) {
        websocket.addEventListener('message', callback);
        listeners.push(callback);

        return this;
    }
};
