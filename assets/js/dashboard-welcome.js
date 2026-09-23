'use strict';
(() => {
  const root = document.querySelector('[data-dashboard-briefing]');
  if (!root) return;
  const clock = root.querySelector('[data-briefing-clock]'),
    weather = root.querySelector('[data-briefing-weather]'),
    refresh = root.querySelector('[data-briefing-refresh]');
  const started = performance.now(),
    serverTime = Number(root.dataset.serverTime);
  const formatter = new Intl.DateTimeFormat('en-PH', {
    timeZone: root.dataset.timezone,
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
  function tick() {
    const now = new Date(serverTime + performance.now() - started);
    clock.dateTime = now.toISOString();
    clock.textContent = formatter.format(now) + ' · ' + root.dataset.timezone;
  }
  tick();
  setInterval(tick, 1000);
  async function loadWeather() {
    if (refresh.disabled) return;
    refresh.disabled = true;
    weather.textContent = 'Detecting your location. Allow location access for local weather.';
    try {
      const location = await window.assistantWelcomeLocation('hello');
      weather.textContent = 'Loading local weather…';
      const response = await fetch('api.php?action=welcome-weather', {
        method: 'POST',
        body: new URLSearchParams({ csrf: root.querySelector('[name=csrf]').value, ...location }),
        headers: { Accept: 'application/json' },
      });
      if (response.redirected || response.status === 401)
        throw Error('Sign in again to refresh weather.');
      if (!response.headers.get('content-type')?.includes('application/json'))
        throw Error('Weather could not load. Please try again.');
      const result = await response.json();
      if (!response.ok) throw Error(result.error || 'Weather could not load.');
      if (typeof result.weather !== 'string')
        throw Error('Weather could not load. Please try again.');
      weather.textContent = result.weather;
    } catch (error) {
      weather.textContent = error.message || 'Weather could not load. Please try again.';
    } finally {
      refresh.disabled = false;
    }
  }
  refresh.addEventListener('click', loadWeather);
  loadWeather();
})();
