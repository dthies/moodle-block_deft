/*
 * Change comments
 *
 * @package    block_deft
 * @module     block_deft/comment
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from "core_form/modalform";
import {get_string as getString} from "core/str";

export default {

    /**
     * Listen for comment actions
     *
     * @param {int} contextid Context id of block
     * @param {string} selector Content location to replace
     * @param {object} refresh Interface to update content
     */
    init: function(contextid, selector, refresh) {
        document.querySelector(selector).addEventListener('click', (e) => {
            const button = e.target.closest('[data-type="comments"] [data-action]');
            if (button) {
                let modalForm;
                e.preventDefault();
                e.stopPropagation();
                switch (button.getAttribute('data-action')) {
                    case 'addcomment':
                        modalForm = new ModalForm({
                            formClass: "block_deft\\form\\add_comment",
                            args: {
                                contextid: contextid,
                                id: button.closest('[data-task]').getAttribute('data-task')
                            },
                            modalConfig: {
                                title: getString('addcomment')
                            }
                        });
                        break;
                    case 'delete':
                        modalForm = new ModalForm({
                            formClass: "block_deft\\form\\delete_comment",
                            args: {
                                commentid: button.closest('[data-comment]').getAttribute('data-comment'),
                                contextid: contextid,
                                id: button.closest('[data-task]').getAttribute('data-task')
                            },
                            modalConfig: {
                                title: getString('confirm')
                            }
                        });
                        break;
                    case 'collapse':
                        button.closest('[data-modified]').setAttribute('data-modified', 0);
                        button.closest('[data-type]').querySelector('.block_deft_comments').classList.remove('expanded');
                        refresh.update();
                        return;
                    case 'expand':
                        button.closest('[data-modified]').setAttribute('data-modified', 0);
                        button.closest('[data-type]').querySelector('.block_deft_comments').classList.add('expanded');
                        refresh.update();
                        return;
                }
                modalForm.show();
            }
        });
    }
};
