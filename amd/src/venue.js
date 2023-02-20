/*
 * Handle events for venue task
 *
 * @package    block_deft
 * @module     block_deft/venue
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import Fragment from 'core/fragment';
import {get_string as getString} from 'core/str';
import ModalEvents from 'core/modal_events';
import ModalFactory from 'core/modal_factory';
import Notification from 'core/notification';
import Templates from 'core/templates';

let venue = null;

/**
 * Handle button click
 *
 * @param {Event} e Change event
 */
const handleClick = (e) => {
    'use strict';

    let button = e.target.closest('.block_deft_venue button[data-action]');
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
                if (button.getAttribute('data-type') === 'modal') {
                    const contextid = button.getAttribute('data-contextid');
                    document.querySelectorAll('.venue_manager').forEach(container => {
                        container.innerHTML = '';
                    });
                    ModalFactory.create({
                        large: true,
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: getString('venue', 'block_deft'),
                        body: '<div class="venue_manager"></div>',
                    }).then(function(modal) {
                        const root = modal.getRoot();
                        venue = modal;
                        modal.setSaveButtonText(getString('hide'));
                        modal.setButtonText('cancel', getString('leave', 'block_deft'));
                        document.querySelector('body').classList.add('block_deft_venue_page');
                        root.on(ModalEvents.cancel, function() {
                            Ajax.call([{
                                args: {
                                    mute: false,
                                    "status": true
                                },
                                fail: Notification.exception,
                                methodname: 'block_deft_venue_settings'
                            }]);
                            Templates.replaceNodeContents(root[0], '', '');
                        });
                        Fragment.loadFragment(
                            'block_deft',
                            'venue_manager',
                            contextid,
                            {
                                taskid: task
                            }
                        ).done((html, js) => {
                            Templates.replaceNodeContents(root[0].querySelector('.venue_manager'), html, js);
                        })
                            .catch(Notification.exception);
                        modal.show();
                        root.on('venueclosed', () => {
                            modal.hide();
                            Templates.replaceNodeContents(root[0], '', '');
                            document.querySelector('body').classList.remove('block_deft_venue_page');
                        });

                        return modal;
                    }).fail(Notification.exception);
                } else {
                    document.querySelector('body').classList.remove('block_deft_venue_page');
                    window.open(url, 'block_deft_venue', 'popup,height=400,width=600');
                }
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
            case 'show':
                venue.show();
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
        document.activeElement.blur();
    }
};

/**
 * Initialize listeners
 */
export const init = () => {
    'use strict';

    document.removeEventListener('click', handleClick);
    document.addEventListener('click', handleClick);
};
