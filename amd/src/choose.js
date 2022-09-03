/*
 * Handle for change for choice seletion
 *
 * @package    block_deft
 * @module     block_deft/choose
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Log from 'core/log';

var contextid;

/**
 * Handle selection change
 *
 * @param {Event} e Change event
 */
const handleChange = (e) => {
    let form = e.target.closest('.deft-choice-selector form');
    if (form) {
        let formdata = new FormData(form),
            id = formdata.get('id'),
            component = form.closest('[data-component]')
                && form.closest('[data-component]').getAttribute('data-component')
                || 'block_deft',
            option = formdata.get('option');
        e.stopPropagation();
        e.preventDefault();
        Fragment.loadFragment(
            component,
            'choose',
            contextid,
            {
                id: id,
                option: option
            }
        ).catch(Log.debug);
        document.activeElement.blur();
    }
};

/**
 * Initialize listeners
 *
 * @param {int} id Context id of content bank
 */
export const init = (id) => {
    'use strict';

    contextid = id;

    document.removeEventListener('change', handleChange);
    document.addEventListener('change', handleChange);
};
