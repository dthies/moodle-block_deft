/*
 * Handle events for venue task
 *
 * @package    block_deft
 * @module     block_deft/choose
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Log from 'core/log';
import Config from 'core/config';
import Notification from 'core/notification';

/**
 * Handle button click
 *
 * @param {Event} e Change event
 */
const handleClick = (e) => {
    'use strict';

    let button = e.target.closest('.block_deft_venue a[data-action]');
    if (button) {
        let task = button.closest('[data-task]').getAttribute('data-task'),
            url = new URL(Config.wwwroot + '/blocks/deft/venue.php');
        url.searchParams.set('task', task);
        switch (button.getAttribute('data-action')) {
            case 'close':
                Ajax.call([{
                    args: {
                        mute: false,
                        "status": true
                    },
                    fail: Notification.exception,
                    methodname: 'block_deft_venue_settings'
                }]);
                break;
            case 'join':
                window.open(url, 'block_deft_venue', 'popup,height=400,width=600');
                break;
            case 'mute':
                Ajax.call([{
                    args: {
                        mute: true,
                        "status": false
                    },
                    fail: Notification.exception,
                    methodname: 'block_deft_venue_settings'
                }]);
                break;
            case 'unmute':
                Ajax.call([{
                    args: {
                        mute: false,
                        "status": false
                    },
                    fail: Notification.exception,
                    methodname: 'block_deft_venue_settings'
                }]);
                break;
            default:
                return;
        }
        e.stopPropagation();
        e.preventDefault();
        Log.debug(e);
        document.activeElement.blur();
    }
};

/**
 * Initialize listeners
 *
 */
export const init = () => {
    'use strict';

    document.removeEventListener('click', handleClick);
    document.addEventListener('click', handleClick);
};
