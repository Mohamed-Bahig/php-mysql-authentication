function showFormMessage(formType, messages, kind = 'error') {
    const box = document.querySelector(`[data-message-for="${formType}"]`);
    if (!box) return;

    box.replaceChildren();
    box.className = `form-message ${kind}`;
    box.hidden = false;

    if (messages.length === 1) {
        box.textContent = messages[0];
        return;
    }

    const heading = document.createElement('strong');
    heading.textContent = kind === 'error' ? 'Please fix the following:' : '';
    if (heading.textContent) box.append(heading);

    const list = document.createElement('ul');
    messages.forEach((message) => {
        const item = document.createElement('li');
        item.textContent = message;
        list.append(item);
    });
    box.append(list);
}

function validateForm(form) {
    const errors = [];
    const fields = [...form.querySelectorAll('input:not([type="hidden"])')];

    fields.forEach((field) => {
        if (field.value.trim() === '') {
            const label = field.labels?.[0]?.textContent?.trim() || field.name;
            errors.push(`${label} is required.`);
        }
    });

    const email = form.querySelector('input[type="email"]');
    if (email && email.value.trim() !== '' && !email.validity.valid) {
        errors.push('Enter a valid email address.');
    }

    if (form.dataset.formType === 'register') {
        const password = form.elements.password.value;
        const confirmation = form.elements.confirm_password.value;
        if (password && confirmation && password !== confirmation) {
            errors.push('Password and confirm password must be the same.');
        }
    }

    return errors;
}

document.querySelectorAll('.validate-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formType = form.dataset.formType;
        const clientErrors = validateForm(form);
        if (clientErrors.length) {
            showFormMessage(formType, clientErrors);
            return;
        }

        const messageBox = document.querySelector(`[data-message-for="${formType}"]`);
        if (messageBox) messageBox.hidden = true;

        try {
            const formUrl = form.getAttribute('action') || window.location.href;
            const response = await fetch(formUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                showFormMessage(formType, result.errors || ['The request could not be completed.']);
                return;
            }

            const welcomeBox = document.querySelector('#welcome-message');
            const welcomeText = document.querySelector('#welcome-text');
            if (welcomeBox && welcomeText && result.message) {
                welcomeText.textContent = result.message;
                welcomeBox.hidden = false;
            }

            if (formType === 'register') form.reset();
            if (formType === 'login') form.elements.password.value = '';
        } catch (error) {
            showFormMessage(formType, ['Could not contact the server. Check that Apache and MySQL are running.']);
        }
    });
});

const logoutForm = document.querySelector('[data-logout-form]');
if (logoutForm) {
    logoutForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const formUrl = logoutForm.getAttribute('action') || window.location.href;
            const response = await fetch(formUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(logoutForm),
            });
            const result = await response.json();
            if (response.ok && result.success) {
                document.querySelector('#welcome-message').hidden = true;
            }
        } catch (error) {
            window.location.reload();
        }
    });
}
