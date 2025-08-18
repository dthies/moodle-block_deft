import DynamicForm from 'core_form/dynamicform';
import Notification from 'core/notification';

export default class {
    constructor(contextid, type, task) {
        const dynamicForm = new DynamicForm(
            document.querySelector('[data-id="' + task + '"] [data-region="status"] div.row'),
            'block_deft\\form\\status_' + type
        );

        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
            e.preventDefault();
            dynamicForm.load({
                contextid: contextid,
                id: task
            }).then(() => {
                const form = document.querySelector('[data-id="' + task + '"] [data-region="status"] form'),
                    data = new FormData(form),
                    params = new URLSearchParams(data);
                form.setAttribute('data-value', params.toString());
                document.body.dispatchEvent(new CustomEvent('deftaction', { }));

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
