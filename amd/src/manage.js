/*
 * Manage task user interface handlers
 *
 * @package    block_deft
 * @module     block_deft/manage
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Templates from 'core/templates';
import Log from 'core/log';
import {get_string as getString} from 'core/str';

var contextid;

/**
 * Submit data for processing
 *
 * @param {Event} e Event object
 */
const submitForm = (e) => {
    Log.debug('Form submitted');
    Log.debug(e.detail);
    if (e.detail && e.detail.html) {
        Templates.replaceNodeContents(
            document.querySelector('.tasks'),
            e.detail.html
        );
    }
};

/**
 * Handle submission actions
 *
 * @param {Event} e Event object
 */
const handleSubmit = (e) => {
    if (e.target.matches('form')) {
        let formdata = new FormData(e.target),
            id = formdata.get('id'),
            type = formdata.get('type') || e.submitter.value,
            title,
            action = e.submitter.name === 'action' && e.submitter.value;
        e.preventDefault();
        e.stopPropagation();
        if (action === 'delete' || action === 'move') {
            type = action;
        }
        switch (action) {
            case 'delete':
                title = getString('confirm', 'core');
                break;
            case 'move':
                title = getString('move', 'core');
                break;
            case 'status':
                title = getString('changestatus', 'block_deft');
                break;
            default:
                title = getString('edit' + type, 'block_deft');
                break;
        }
        Log.debug('Create ' + type);
        const modalForm = new ModalForm({
            formClass: "block_deft\\form\\" + (action === 'status' ? "status_" : "edit_") + type,
            args: {
                contextid: contextid,
                id: id
            },
            modalConfig: {
                title: title
            }
        });
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, submitForm);
        modalForm.show();
    }
};

/**
 * Initialize listeners
 *
 * @param {int} id Context id of block
 */
export const init = (id) => {
    'use strict';

    contextid = id;

    document.removeEventListener('submit', handleSubmit);
    document.addEventListener('submit', handleSubmit);
};
