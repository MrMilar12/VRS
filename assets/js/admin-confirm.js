'use strict';
(() => {
  const dialog = document.getElementById('admin-confirm-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const prompt = document.getElementById('admin-confirm-form'),
    password = document.getElementById('admin-confirm-password'),
    confirmButton = document.getElementById('admin-confirm-submit');
  let pending = null;
  const reset = () => {
    password.value = '';
    if (pending) pending.input.value = '';
    pending = null;
  };
  dialog
    .querySelectorAll('[data-admin-cancel]')
    .forEach((button) => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', reset);
  dialog.addEventListener('cancel', reset);
  window.addEventListener('pageshow', () => {
    dialog.close();
    reset();
  });
  document.querySelectorAll('input[name="confirmation_password"]').forEach((input) => {
    const form = input.form,
      label = input.closest('label');
    if (!form || !label) return;
    // Keep the original inline field as a working fallback when JavaScript is unavailable.
    label.hidden = true;
    input.required = false;
    input.type = 'hidden';
    input.value = '';
    form.dataset.adminConfirm = '';
    let verified = false;
    form.addEventListener('submit', (event) => {
      if (verified) {
        verified = false;
        return;
      }
      event.preventDefault();
      const submitter = event.submitter,
        action = form.elements.namedItem('action')?.value,
        decision = submitter?.value;
      const rejecting = action === 'review_registration' && decision === 'reject',
        deleting = action === 'delete_record';
      const title =
        action === 'review_registration'
          ? rejecting
            ? 'Reject account request?'
            : 'Approve account request?'
          : deleting
            ? 'Delete user account?'
            : 'Save account changes?';
      document.getElementById('admin-confirm-title').textContent = title;
      document.getElementById('admin-confirm-description').textContent =
        action === 'review_registration'
          ? rejecting
            ? 'This person will remain unable to access the system.'
            : 'This person will receive Requester access and can sign in.'
          : deleting
            ? 'This permanently deletes the selected account. This cannot be undone.'
            : 'Confirm the account details and access settings you are saving.';
      document.getElementById('admin-confirm-account').textContent =
        form.closest('.registration-review')?.querySelector('h3')?.textContent ||
        form.elements.namedItem('full_name')?.value ||
        form.closest('.form-body')?.querySelector('h2')?.textContent ||
        'Selected account';
      confirmButton.textContent = rejecting
        ? 'Reject account'
        : deleting
          ? 'Delete account'
          : action === 'review_registration'
            ? 'Approve account'
            : 'Save changes';
      confirmButton.className = 'btn ' + (rejecting || deleting ? 'danger' : 'primary');
      password.value = '';
      pending = {
        input,
        form,
        submitter,
        authorize: () => {
          verified = true;
        },
        clear: () => {
          verified = false;
        },
      };
      dialog.showModal();
      password.focus();
    });
  });
  prompt.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!pending || !prompt.reportValidity()) return;
    const current = pending,
      secret = password.value;
    pending = null;
    password.value = '';
    dialog.close();
    current.input.value = secret;
    current.authorize();
    try {
      current.form.requestSubmit(current.submitter || undefined);
    } finally {
      current.clear();
      current.input.value = '';
    }
  });
})();
