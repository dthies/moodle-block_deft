/*
 * Refresh content when changed on the system
 *
 * @package    block_deft
 * @module     block_deft/refresh
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from "core/fragment";
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
            comments = false,
            component = content.closest('[data-component]')
                && content.closest('[data-component]').getAttribute('data-component')
                || 'block_deft',
            data = {};
        if (!content) {
            return;
        }
        content.querySelectorAll('.block_deft_comments textarea').forEach((textarea) => {
            if (
                textarea.value != textarea.getAttribute('aria-label')
                || !textarea.value
            ) {
                // User is writing a comment.
                comments = true;
            }
        });

        if (
            comments
            || (this.lastupdate + throttle > Date.now())
            || (document.activeElement.closest(selector) && document.activeElement.closest('select'))
        ) {
            if (
                !this.throttled
                || (this.lastupdate + throttle < Date.now())
            ) {
                setTimeout(() => {
                    this.refresh(contextid, selector, throttle);
                }, Math.max(this.lastupdate + throttle - Date.now(), 40));
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

        Fragment.loadFragment(
            component,
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
