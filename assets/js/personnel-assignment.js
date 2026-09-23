'use strict';
(() => {
  const select = document.querySelector('[data-personnel-assignment]');
  if (!select) return;
  const form = select.form,
    check = form.querySelector('[data-personnel-check]'),
    result = form.querySelector('[data-personnel-result]'),
    approve = form.querySelector('[data-personnel-approve]');
  const selections = form.querySelector('[data-personnel-selections]'),
    add = form.querySelector('[data-personnel-add]');
  const unavailable = new Set(
    Array.from(select.options)
      .filter((option) => option.disabled)
      .map((option) => option.value),
  );
  const dropdowns = () => Array.from(selections.querySelectorAll('select'));
  function updateChoices() {
    const fields = dropdowns(),
      chosen = fields.map((field) => field.value).filter(Boolean);
    fields.forEach((field) => {
      Array.from(field.options).forEach((option) => {
        option.disabled =
          unavailable.has(option.value) ||
          (option.value !== field.value && chosen.includes(option.value));
      });
      field.closest('.personnel-selection-row').querySelector('[data-personnel-remove]').hidden =
        fields.length === 1;
    });
    add.disabled =
      fields.some((field) => !field.value) ||
      !Array.from(select.options).some(
        (option) =>
          option.value && !unavailable.has(option.value) && !chosen.includes(option.value),
      );
  }
  add.hidden = false;
  add.addEventListener('click', () => {
    const row = selections.firstElementChild.cloneNode(true),
      field = row.querySelector('select');
    field.value = '';
    selections.append(row);
    updateChoices();
    refresh();
    field.focus();
  });
  selections.addEventListener('change', () => {
    updateChoices();
    refresh();
  });
  selections.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-personnel-remove]');
    if (!remove) return;
    remove.closest('.personnel-selection-row').remove();
    updateChoices();
    refresh();
  });
  let version = 0,
    controller;
  async function refresh() {
    const current = ++version;
    controller?.abort();
    approve.disabled = true;
    check.disabled = false;
    const fields = dropdowns(),
      ids = fields.map((field) => field.value).filter(Boolean);
    if (!ids.length || ids.length !== fields.length) {
      result.className = 'muted';
      result.textContent = 'Select personnel to check availability.';
      return;
    }
    controller = new AbortController();
    const request = controller,
      timeout = setTimeout(() => request.abort(), 12000);
    check.disabled = true;
    result.className = 'muted';
    result.textContent = 'Checking personnel availability…';
    try {
      const params = new URLSearchParams({
        action: 'personnel_availability',
        id: select.dataset.personnelAssignment,
      });
      ids.forEach((id) => params.append('personnel_ids[]', id));
      const response = await fetch('api.php?' + params, { signal: request.signal });
      const data = await response.json();
      if (current !== version) return;
      if (!response.ok || data.error)
        throw new Error(data.error || 'Unable to check availability.');
      approve.disabled = !data.available;
      result.className = 'alert ' + (data.available ? 'success' : 'error');
      result.textContent = data.message;
    } catch (error) {
      if (current === version) {
        result.className = 'alert error';
        result.textContent = 'Unable to check availability. Try Check availability again.';
      }
    } finally {
      clearTimeout(timeout);
      if (current === version) check.disabled = false;
    }
  }
  updateChoices();
  check.addEventListener('click', refresh);
  refresh();
})();
