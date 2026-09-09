'use strict';
document.getElementById('test-connection')?.addEventListener('click', async event => {
    event.preventDefault();
    const button = event.currentTarget;
    const form = button.form;
    const result = document.getElementById('connection-result');
    const data = new FormData(form);
    data.set('action', 'test');
    // Connection tests need only database credentials, not the new administrator password.
    for (const key of ['admin_password', 'confirm_password']) data.delete(key);
    button.disabled = true;
    result.className = 'alert';
    result.textContent = 'Checking your MySQL connection…';
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {Accept: 'application/json'},
            credentials: 'same-origin',
        });
        const status = await response.json();
        result.className = 'alert ' + (status.success ? 'success' : 'error');
        result.textContent = status.message;
    } catch {
        result.className = 'alert error';
        result.textContent = 'The connection check could not finish. Check the server and try again. Your form entries have been kept.';
    } finally {
        button.disabled = false;
    }
});
