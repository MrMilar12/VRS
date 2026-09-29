'use strict';
// Location is requested only for a welcome greeting, with browser permission.
window.assistantWelcomeLocation = async (message) => {
  if (
    !/^(hi|hello|hey|good morning|good afternoon|good evening|kumusta|kamusta|hello po|hi po)[!?. ]*$/i.test(
      message.trim(),
    )
  )
    return {};
  if (!window.isSecureContext) return { weather_location_error: 'insecure' };
  if (!navigator.geolocation) return { weather_location_error: 'unsupported' };
  return new Promise((resolve) => {
    let settled = false;
    const finish = (value) => {
      if (!settled) {
        settled = true;
        clearTimeout(timer);
        resolve(value);
      }
    };
    const timer = setTimeout(() => finish({ weather_location_error: 'timeout' }), 30000);
    try {
      navigator.geolocation.getCurrentPosition(
        (position) =>
          finish({
            weather_latitude: position.coords.latitude.toFixed(2),
            weather_longitude: position.coords.longitude.toFixed(2),
          }),
        (error) =>
          finish({
            weather_location_error:
              error.code === 1 ? 'denied' : error.code === 3 ? 'timeout' : 'unavailable',
          }),
        { enableHighAccuracy: false, timeout: 20000, maximumAge: 300000 },
      );
    } catch (error) {
      finish({ weather_location_error: 'unavailable' });
    }
  });
};
const sidebar = document.querySelector('#sidebar');
const sidebarToggle = document.querySelector('[data-toggle-sidebar]');
const sidebarBackdrop = document.querySelector('[data-close-sidebar]');
if (sidebar && sidebarToggle && sidebarBackdrop) {
  const mobileNavigation = window.matchMedia('(max-width: 720px)');
  const setNavigation = (open, restoreFocus = false) => {
    const expanded = mobileNavigation.matches && open;
    sidebar.classList.toggle('open', expanded);
    sidebar.inert = mobileNavigation.matches && !expanded;
    sidebarToggle.setAttribute('aria-expanded', String(expanded));
    sidebarBackdrop.hidden = !expanded;
    document.body.classList.toggle('navigation-open', expanded);
    if (expanded) sidebar.querySelector('a')?.focus();
    else if (restoreFocus) sidebarToggle.focus();
  };
  sidebarToggle.addEventListener('click', () => setNavigation(!sidebar.classList.contains('open')));
  sidebarBackdrop.addEventListener('click', () => setNavigation(false, true));
  sidebar.addEventListener('click', (event) => {
    if (event.target.closest('a')) setNavigation(false);
  });
  document.addEventListener('keydown', (event) => {
    if (!sidebar.classList.contains('open')) return;
    if (event.key === 'Escape') setNavigation(false, true);
    if (event.key === 'Tab') {
      const links = [...sidebar.querySelectorAll('a[href], button:not([disabled])')];
      const first = links[0], last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
    }
  });
  mobileNavigation.addEventListener('change', () => setNavigation(false));
  setNavigation(false);
}
document.querySelectorAll('form[data-confirm]').forEach((form) =>
  form.addEventListener('submit', (event) => {
    if (!form.hasAttribute('data-admin-confirm') && !confirm(form.dataset.confirm))
      event.preventDefault();
  }),
);
document
  .querySelectorAll('[data-close-dialog]')
  .forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('input[name="start_datetime"]').forEach((input) => {
  const end = input.form.querySelector('input[name="end_datetime"]');
  if (!end) return;
  const validate = () => {
    end.min = input.value;
    end.setCustomValidity(
      input.value && end.value && end.value <= input.value
        ? 'End date and time must be after the start date and time.'
        : '',
    );
  };
  input.addEventListener('input', validate);
  input.addEventListener('change', validate);
  end.addEventListener('input', validate);
  end.addEventListener('change', validate);
  validate();
});

// Keep trip details on screen if validation, the database, or hosting rejects a save.
document.addEventListener('submit', async (event) => {
  const form = event.target;
  if (
    !(form instanceof HTMLFormElement) ||
    !form.matches('.request-form,[data-booking-review]') ||
    form.elements.namedItem('action')?.value !== 'save_request' ||
    event.defaultPrevented
  )
    return;
  event.preventDefault();
  if (form.dataset.saving === 'true') return;
  const data = new FormData(form),
    submitter = event.submitter;
  if (submitter?.name) data.set(submitter.name, submitter.value);
  let notice = form.querySelector('[data-save-status]');
  if (!notice) {
    notice = document.createElement('p');
    notice.dataset.saveStatus = '';
    notice.className = 'alert';
    notice.setAttribute('role', 'status');
    notice.tabIndex = -1;
    const anchor = form.querySelector('.form-actions') || form.querySelector('button[type=submit]');
    if (anchor) anchor.before(notice);
    else form.prepend(notice);
  }
  notice.textContent = 'Saving your requisition…';
  notice.className = 'alert';
  const buttons = [...form.querySelectorAll('button')].map((button) => [button, button.disabled]);
  buttons.forEach(([button]) => (button.disabled = true));
  form.dataset.saving = 'true';
  form.setAttribute('aria-busy', 'true');
  try {
    // A control named "action" shadows the form.action DOM property.
    const endpoint = new URL(form.getAttribute('action') || location.href, document.baseURI);
    const response = await fetch(endpoint.href, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (response.redirected || !response.headers.get('content-type')?.includes('application/json'))
      throw new Error(
        'The server interrupted the save or returned an unexpected page. Your entries are still here. Open VRS in another tab to check your sign-in and requisition list before trying again.',
      );
    const result = await response.json();
    if (!response.ok)
      throw new Error(
        result.error || 'The requisition could not be saved. Your entries are still here.',
      );
    if (typeof result.redirect !== 'string')
      throw new Error(
        'The save could not be confirmed. Check your requisition list before trying again.',
      );
    const target = new URL(result.redirect, location.href);
    if (target.origin !== location.origin)
      throw new Error(
        'The save returned an unexpected destination. Check your requisition list before trying again.',
      );
    location.assign(target.href);
  } catch (error) {
    notice.textContent =
      error instanceof TypeError
        ? 'The connection was interrupted. Your entries are still here. Check your requisition list before trying again.'
        : error.message;
    notice.className = 'alert error';
    notice.focus();
  } finally {
    buttons.forEach(([button, disabled]) => (button.disabled = disabled));
    delete form.dataset.saving;
    form.removeAttribute('aria-busy');
  }
});
