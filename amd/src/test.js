/*
 * Test the connection with message service
 *
 * @package    block_deft
 * @module     block_deft/test
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Fragment from "core/fragment";
import Log from "core/log";
import Notification from "core/notification";
import Socket from "block_deft/socket";
import {get_string as getString} from 'core/str';
import * as Toast from 'core/toast';

export default {

    /**
     * Test connection
     *
     * @param {int} contextid Context id of block
     */
    init: function(contextid) {
        Log.debug('Requesting token');
        Ajax.call([{
            methodname: 'block_deft_renew_token',
            args: {contextid: contextid},
            done: (token) => {
                const socket = new Socket(contextid, token.token);
                getString('authorizationreceived', 'block_deft').done((message) => {
                    Toast.add(message, {'type': 'info'});
                });
                socket.subscribe((e) => {
                    if (e.type === 'message') {
                        getString('messagereceived', 'block_deft').done((message) => {
                            Toast.add(message, {'type': 'success'});
                        });
                    }
                });

                setTimeout(() => {
                    Fragment.loadFragment(
                        'block_deft',
                        'test',
                        contextid,
                        {}
                    ).done((message) => {
                        Toast.add(message, {'type': 'info'});
                    }).fail(Notification.exception);
                }, 500);
            },
            fail: Notification.exception
        }]);
    }
};
