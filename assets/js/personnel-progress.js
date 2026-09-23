'use strict';
(() => {
  const panel = document.querySelector('[data-personnel-progress]');
  if (!panel) return;
  const button = panel.querySelector('[data-progress-submit]'),
    guidance = panel.querySelector('#progress-guidance'),
    clock = panel.querySelector('[data-progress-clock]');
  if (!button) return;
  const serverNow = Number(panel.dataset.serverNow),
    start = Number(panel.dataset.start),
    end = Number(panel.dataset.end),
    loadedAt = performance.now();
  if (![serverNow, start, end].every(Number.isFinite)) return;
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: panel.dataset.timezone,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
  });
  const update = () => {
    const now = serverNow + performance.now() - loadedAt;
    clock.textContent =
      'Current time: ' + formatter.format(new Date(now)) + ' (' + panel.dataset.timezone + ')';
    if (panel.dataset.starting !== '1') return;
    const ready = now < end;
    button.disabled = !ready;
    guidance.className = ready ? 'field-hint' : 'alert';
    if (now < start) {
      guidance.textContent =
        'You can start early. The actual start time is recorded and personnel availability is checked from now.';
    } else
      guidance.textContent = ready
        ? 'Ready to start. This records the current time for all assigned personnel and checks their availability.'
        : 'The scheduled end has passed. This assignment can no longer be started; submit a new requisition for a revised schedule.';
  };
  update();
  setInterval(update, 1000);
  document.addEventListener('visibilitychange', update);
})();
