import DynamicForm from 'core_form/dynamicform';
import Log from 'core/log';
import Notification from 'core/notification';

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
            }).then(() => {
                const form = document.querySelector('[data-id="' + task + '"] [data-region="status"] form'),
                    data = new FormData(form),
                    params = new URLSearchParams(data);
                form.setAttribute('data-value', params.toString());
                return true;
            }).fail(Notification.exception);
        });

        document.querySelectorAll('[data-id="' + task + '"] [data-region="status"] form').forEach((form) => {
            const data = new FormData(form),
                params = new URLSearchParams(data);
            form.setAttribute('data-value', params.toString());
        });
    }
}
