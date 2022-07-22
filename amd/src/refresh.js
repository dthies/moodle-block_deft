/*
 * Refresh content when changed on the system
 *
 * @package    block_deft
 * @module     block_deft/refresh
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from "core/fragment";
import Log from "core/log";
import Notification from "core/notification";
import Socket from "block_deft/socket";
import Templates from "core/templates";

export default {

    throttled: false,

    lastupdate: 0,

    /**
     * Listen to WebSocket and refresh content
     *
     * @param {int} contextid Context id of block
     * @param {string} selector Content location to replace
     * @param {string} token Authentication token to connect service
     * @param {int} throttle Throttle dely in ms
     */
    init: function(contextid, selector, token, throttle) {
        Socket.open(contextid, token).subscribe(() => {
            this.refresh(contextid, selector, throttle);
        });
    },

    /**
     * Refresh content
     *
     * @param {int} contextid Context id of block
     * @param {string} selector Content location to replace
     * @param {int} throttle Throttle dely in ms
     */
    refresh: function(contextid, selector, throttle) {
        let content = document.querySelector(selector).parentNode,
            data = {};
        if (!content)  {
            return;
        }

        if (this.lastupdate + throttle > Date.now()) {
            if (!this.throttled) {
                setTimeout(() => {
                    this.refresh(contextid, selector, throttle);
                }, this.lastupdate + throttle - Date.now());
                this.throttled = true;
            }

            return;
        }

        document.querySelector(selector)
            .querySelectorAll('a.comment-link[aria-expanded="true"]')
            .forEach((opencomments) => {
                data.opencomments = data.opencomments || [];
                data.opencomments.push(opencomments.closest('[data-task]').getAttribute('data-task'));
            });

        Log.debug(data);
        Fragment.loadFragment(
            'block_deft',
            'content',
            contextid,
            {
                jsondata: JSON.stringify(data)
            }
        ).done(
            Templates.replaceNodeContents.bind(Templates, content)
        ).fail(Notification.exception);

        this.throttled = false;
        this.lastupdate = Date.now();
    }
};
