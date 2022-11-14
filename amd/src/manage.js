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
    'use strict';

    Log.debug('Form submitted');
    if (e.detail && e.detail.order) {
        e.detail.order.forEach((task) => {
            document.querySelector('.tasks > div').appendChild(
                document.querySelector('.tasks [data-id="' + task + '"]').parentNode
            );
        });
        return;
    }
    if (e.detail && e.detail.id) {
        if (document.querySelector('[data-id="' + e.detail.id + '"] [data-region="taskinfo"]')) {
            if (e.detail.html) {
                Templates.replaceNodeContents(
                    document.querySelector('[data-id="' + e.detail.id + '"] [data-region="taskinfo"]'),
                    e.detail.html,
                    ''
                );
            } else {
                document.querySelector('.tasks [data-id="' + e.detail.id + '"]').parentNode.remove(true);
            }

            return;
        }
        Templates.render('block_deft/task', e.detail).done((html, js) => {
            const node = document.createElement('div');
            node.innerHTML = html;
            document.querySelector('.tasks > div').appendChild(node.firstChild);
            Templates.runTemplateJS(js);
        });
    }
};

/**
 * Handle submission actions
 *
 * @param {Event} e Event object
 */
const handleSubmit = (e) => {
    'use strict';

    if (e.target.matches('form') && !e.target.closest('[data-region="status"]')) {
        let formdata = new FormData(e.target),
            component = e.target.closest('[data-component]')
                && e.target.closest('[data-component]').getAttribute('data-component')
                || 'block_deft',
            id = formdata.get('id') || 0,
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
            case 'saveall':
                document.querySelectorAll('[data-region="status"] form.modified input[type="submit"]').forEach((form) => {
                    form.click();
                });
                return;
            case 'status':
                title = getString('changestatus', 'block_deft');
                break;
            default:
                title = getString('edit' + type, 'block_deft');
                break;
        }
        Log.debug('Create ' + type);
        let modalForm = new ModalForm({
            formClass: component + "\\form\\" + (action === 'status' ? "status_" : "edit_") + type,
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
 * Handle form change
 *
 * @param {Event} e Event object
 */
const handleChange = (e) => {
    'use strict';

    let form = e.target.closest('[data-region="status"] form');
    if (form) {
        const data = new FormData(form),
            params = new URLSearchParams(data);
        if (form.getAttribute('data-value') === params.toString()) {
           form.classList.remove('modified');
        } else {
           form.classList.add('modified');
        }
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

    document.removeEventListener('change', handleChange);
    document.addEventListener('change', handleChange);
};
