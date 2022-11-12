import DynamicForm from 'core_form/dynamicform';
import Log from 'core/log';

export default class {
    constructor(contextid, type, task) {
        const dynamicForm = new DynamicForm(
            document.querySelector('[data-id="' + task + '"] [data-region="status"]'),
            'block_deft\\form\\status_' + type
        );

        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
            e.preventDefault();
            const response = e.detail;
            Log.debug(response);
            dynamicForm.load({
                contextid: contextid,
                id: task
            });
        });
    }
}
