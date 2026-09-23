'use strict';
document.querySelectorAll('[data-destinations]').forEach((group) => {
  const rows = group.querySelector('[data-destination-rows]'),
    add = group.querySelector('[data-destination-add]'),
    template = group.querySelector('[data-destination-template]');
  let next = rows.children.length;
  function update() {
    Array.from(rows.children).forEach((row, i) => {
      row.querySelector('[data-destination-label]').textContent = `Place ${i + 1}`;
      const remove = row.querySelector('[data-destination-remove]');
      remove.hidden = rows.children.length === 1;
      remove.setAttribute('aria-label', `Remove place ${i + 1}`);
    });
    add.disabled = rows.children.length >= 20;
  }
  add.hidden = false;
  add.addEventListener('click', () => {
    if (rows.children.length >= 20) return;
    const fragment = template.content.cloneNode(true),
      row = fragment.firstElementChild;
    row.querySelectorAll('*').forEach((element) => {
      ['id', 'for', 'aria-controls', 'aria-describedby'].forEach((attribute) => {
        if (element.hasAttribute(attribute))
          element.setAttribute(
            attribute,
            element.getAttribute(attribute).replaceAll('__INDEX__', String(next)),
          );
      });
    });
    next++;
    rows.append(fragment);
    update();
    document.dispatchEvent(new CustomEvent('destinations:add', { detail: row }));
    row.querySelector('input').focus();
  });
  rows.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-destination-remove]');
    if (!remove || rows.children.length === 1) return;
    const row = remove.closest('[data-destination-row]'),
      focus = row.nextElementSibling || row.previousElementSibling;
    row.remove();
    update();
    focus?.querySelector('input').focus();
  });
  update();
});
