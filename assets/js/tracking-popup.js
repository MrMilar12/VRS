'use strict';
(() => {
  const popup = document.querySelector('#header-help-popup'),
    header = document.querySelector('.header-tracking-search');
  if (!popup || !header) return;
  const form = popup.querySelector('[data-header-help-form]'),
    log = popup.querySelector('[data-header-help-log]'),
    status = popup.querySelector('[data-header-help-status]'),
    send = form.querySelector('[type=submit]');
  const island = document.querySelector('[data-assistant-island]');
  const searchInput = header.querySelector('input[name="q"], input[type="search"]');
  const searchButton = header.querySelector('button[type="submit"], button:not([type])');

  const assistantWelcomeLocation =
    typeof window.assistantWelcomeLocation === 'function'
      ? window.assistantWelcomeLocation.bind(window)
      : async () => ({});

  if (island && searchInput && searchButton) {
    const toggleIsland = (expanded) => {
      island.classList.toggle('is-expanded', expanded);
      header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if (expanded) {
        requestAnimationFrame(() => searchInput.focus({ preventScroll: true }));
      }
    };

    header.addEventListener('pointerdown', (event) => {
      const target = event.target;
      if (island.classList.contains('is-expanded')) return;
      if (target === header || target === island || target === searchInput) {
        event.preventDefault();
        toggleIsland(true);
      }
    });

    const expandFromIcon = (event) => {
      if (island.classList.contains('is-expanded')) return;
      event.preventDefault();
      toggleIsland(true);
    };

    searchButton.addEventListener('click', (event) => {
      const text = searchInput.value.trim();
      if (!island.classList.contains('is-expanded')) {
        if (text && isQuestion(text)) {
          event.preventDefault();
          toggleIsland(true);
          return;
        }
        if (!text || !isQuestion(text)) {
          event.preventDefault();
          toggleIsland(true);
          return;
        }
      }
    });

    searchButton.addEventListener('pointerdown', expandFromIcon);
    searchInput.addEventListener('pointerdown', (event) => {
      if (!island.classList.contains('is-expanded')) {
        event.preventDefault();
        toggleIsland(true);
      }
    });

    window.addEventListener('pointerdown', (event) => {
      const target = event.target;
      const clickedInsideIsland = target instanceof Element && target.closest('[data-assistant-island]');
      const clickedInsidePopup = target instanceof Element && target.closest('#header-help-popup');
      if (!clickedInsideIsland && !clickedInsidePopup && island.classList.contains('is-expanded')) {
        toggleIsland(false);
      }
    });

    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        toggleIsland(false);
      }
    });
  }
  let busy = false,
    opener = null;
  function position() {
    if (!island || popup.hidden) return;
    const rect = island.getBoundingClientRect(),
      width = Math.min(Math.max(rect.width, 360), 560, window.innerWidth - 24),
      left = Math.max(
        12,
        Math.min(rect.left + (rect.width - width) / 2, window.innerWidth - width - 12),
      );
    const viewport = window.visualViewport,
      top = Math.max((viewport?.offsetTop || 0) + 12, rect.top),
      available = Math.max(
        80,
        (viewport?.height || window.innerHeight) + (viewport?.offsetTop || 0) - top - 12,
      );
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.width = width + 'px';
    popup.style.maxHeight = available + 'px';
  }
  if (island) popup.classList.add('island-expanded-panel');
  function isQuestion(text) {
    const value = text.trim();
    if (!value) return false;
    if (/^(hello po|hi po)[!?. ]*$/i.test(value)) return true;
    if (/^(track|check|find|status)\b/i.test(value)) return true;
    if (/^(?:[A-Z]+-)?\d[\w-]*$/i.test(value)) return false;
    if (/[?？]/u.test(value)) return true;
    if (/^(hi|hello|hey|kumusta|kamusta|good morning|good afternoon|good evening)[!. ]*$/i.test(value))
      return true;

    // Keep plain search terms like "request" and "personnel" on the normal
    // tracking route. Only true assistant-style prompts should open the island.
    const firstWord = value.split(/\s+/)[0].toLowerCase();
    return (
      /^(how|what|why|when|where|who|which|can|could|would|should|is|are|do|does|did|has|have|will|please|help|explain|tell|show|book|booking|reserve|reservation|schedule|paano|ano|bakit|kailan|saan|sino|pwede|puwede|maaari|mayroon|meron)\b/i.test(
        value,
      ) ||
      /^(?:how|what|why|when|where|who|which|can|could|would|should|is|are|do|does|did|has|have|will|please|help|explain|tell|show|book|booking|reserve|reservation|schedule|paano|ano|bakit|kailan|saan|sino|pwede|puwede|maaari|mayroon|meron)\b/i.test(firstWord)
    );
  }
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let phase = 'closed',
    motion = [],
    finishMotion = null,
    preview = null;
  function settle() {
    const finish = finishMotion;
    finishMotion = null;
    motion.forEach((animation) => animation.cancel());
    motion = [];
    preview?.remove();
    preview = null;
    popup.classList.remove('is-morphing');
    for (const child of popup.children) child.style.removeProperty('width');
    finish?.();
  }
  function frame(rect, radius) {
    return {
      left: rect.left + 'px',
      top: rect.top + 'px',
      width: rect.width + 'px',
      height: rect.height + 'px',
      borderRadius: radius + 'px',
    };
  }
  function morph(opening, finish, from = null) {
    if (!island || reducedMotion.matches || typeof popup.animate !== 'function') {
      finish();
      return;
    }
    const pill = header.getBoundingClientRect(),
      panel = popup.getBoundingClientRect();
    const start = from || (opening ? pill : panel),
      end = opening ? panel : pill;
    const radius = Number.parseFloat(getComputedStyle(popup).borderTopLeftRadius) || 24;
    const startRadius = opening ? pill.height / 2 : radius,
      endRadius = opening ? radius : pill.height / 2;
    const travel = (amount) => ({
      left: start.left + (end.left - start.left) * amount,
      top: start.top + (end.top - start.top) * amount,
      width: start.width + (end.width - start.width) * amount,
      height: start.height + (end.height - start.height) * amount,
    });
    const compressed = travel(opening ? 0.08 : 0.9);
    const expanded = travel(opening ? 0.78 : 0.28);
    const frames = opening
      ? [
          frame(start, startRadius),
          { ...frame(compressed, startRadius + 4), offset: 0.14 },
          { ...frame(expanded, endRadius + 10), offset: 0.68 },
          frame(end, endRadius),
        ]
      : [
          frame(start, startRadius),
          { ...frame(compressed, startRadius + 8), offset: 0.28 },
          frame(end, endRadius),
        ];
    finishMotion = finish;
    popup.classList.add('is-morphing');
    for (const child of popup.children) child.style.width = Math.max(0, panel.width - 2) + 'px';
    const shell = popup.animate(frames, {
      duration: opening ? 720 : 560,
      easing: 'cubic-bezier(.16,1,.3,1)',
      fill: 'both',
    });
    motion.push(shell);

    popup.classList.remove('bubble-pop-open', 'bubble-pop-close');
    island.classList.remove('bubble-pop-open', 'bubble-pop-close');
    if (!from) {
      preview = header.cloneNode(true);
      preview.removeAttribute('aria-controls');
      preview.removeAttribute('aria-expanded');
      preview.setAttribute('aria-hidden', 'true');
      preview.inert = true;
      preview.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
      Object.assign(preview.style, {
        position: 'fixed',
        left: pill.left + 'px',
        top: pill.top + 'px',
        width: pill.width + 'px',
        height: pill.height + 'px',
        margin: '0',
        maxWidth: 'none',
        zIndex: '91',
        pointerEvents: 'none',
        borderRadius: pill.height / 2 + 'px',
      });
      document.body.append(preview);
      motion.push(
        preview.animate(
          opening ? [{ opacity: 1 }, { opacity: 0 }] : [{ opacity: 0 }, { opacity: 1 }],
          { duration: 180, delay: opening ? 0 : 400, easing: 'ease', fill: 'both' },
        ),
      );
      motion.push(
        preview.animate(
          opening
            ? [{ transform: 'scale(1)' }, { transform: 'scale(.82)' }]
            : [{ transform: 'scale(.82)' }, { transform: 'scale(1)' }],
          { duration: 350, delay: opening ? 0 : 270, easing: 'ease', fill: 'both' },
        ),
      );
    }
    // Match the example's delayed fade and 14px entrance independently of size.
    for (const child of popup.children) {
      motion.push(
        child.animate(
          opening ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 1 }, { opacity: 0 }],
          { duration: 280, delay: opening ? 160 : 0, fill: 'both', easing: 'ease' },
        ),
      );
      motion.push(
        child.animate(
          opening
            ? [{ transform: 'translateY(14px) scale(.985)' }, { transform: 'none' }]
            : [{ transform: 'none' }, { transform: 'translateY(14px) scale(.985)' }],
          {
            duration: 420,
            delay: opening ? 100 : 0,
            fill: 'both',
            easing: 'cubic-bezier(.2,.9,.2,1)',
          },
        ),
      );
    }
    shell.finished.then(
      () => {
        if (finishMotion === finish) settle();
      },
      () => {},
    );
  }
  function open() {
    if (phase === 'open' || phase === 'opening') return;
    settle();
    opener = document.activeElement;
    phase = 'opening';
    popup.hidden = false;
    position();
    island?.classList.add('is-expanded');
    header.setAttribute('aria-expanded', 'true');
    morph(true, () => {
      phase = 'open';
      form.elements.message.focus({ preventScroll: true });
      log.scrollTop = log.scrollHeight;
    });
  }
  function close() {
    if (phase === 'closed' || phase === 'closing') return;
    const from = phase === 'opening' ? popup.getBoundingClientRect() : null;
    settle();
    phase = 'closing';
    morph(
      false,
      () => {
        popup.hidden = true;
        phase = 'closed';
        island?.classList.remove('is-expanded');
        header.setAttribute('aria-expanded', 'false');
        opener?.focus({ preventScroll: true });
      },
      from,
    );
  }
  window.addEventListener('resize', () => {
    settle();
    position();
  });
  window.addEventListener(
    'scroll',
    () => {
      settle();
      position();
    },
    { passive: true },
  );
  reducedMotion.addEventListener('change', settle);
  window.visualViewport?.addEventListener('resize', () => {
    settle();
    position();
  });
  popup.querySelector('[data-header-help-close]').addEventListener('click', close);
  popup.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
  });
  const bubble = (speaker, text) => {
    const node = document.createElement('article'),
      title = document.createElement('strong'),
      body = document.createElement('p');
    node.className =
      'header-help-message message-bubble-enter' + (speaker === 'You' ? ' from-user' : '');
    title.textContent = speaker;
    body.textContent = text;
    node.append(title, body);
    log.append(node);
    return node;
  };
  async function ask(question) {
    if (busy) {
      form.elements.message.value = question;
      status.textContent = 'Please wait for the current reply, then send your next question.';
      return;
    }
    busy = true;
    send.disabled = true;
    status.textContent = '';
    bubble('You', question);
    const thinking = bubble('VPRS assistant', 'Thinking…');
    thinking.classList.add('header-help-thinking');
    thinking.setAttribute('role', 'status');
    form.elements.message.value = '';
    log.scrollTop = log.scrollHeight;
    try {
      const weather = await assistantWelcomeLocation(question);
      const response = await fetch('api.php?action=assistant', {
        method: 'POST',
        body: new URLSearchParams({
          mode: 'popup',
          message: question,
          csrf: form.elements.csrf.value,
          ...weather,
        }),
        headers: { Accept: 'application/json' },
      });
      const redirected = response?.redirected ?? false;
      const statusCode = response?.status ?? 200;
      const ok = response?.ok ?? true;
      if (redirected || statusCode === 401)
        throw Error('Your sign-in expired. Sign in again, then retry your message.');
      const contentType = response?.headers?.get?.('content-type') || '';
      if (contentType && !contentType.includes('application/json'))
        throw Error(
          'The server returned a page instead of an assistant reply. Refresh and retry; if it continues, check the server PHP error log.',
        );
      const result = await response.json();
      if (!ok)
        throw Error(result.error || 'The assistant could not reply. Please try again.');
      if (typeof result?.reply !== 'string')
        throw Error(
          'The server returned an incomplete assistant reply. Please retry your message.',
        );
      thinking.remove();
      const answer = bubble('VPRS assistant', result.reply);
      if (result.open_booking === true) {
        const bookingLink = document.createElement('a');
        bookingLink.className = 'btn primary';
        bookingLink.href = 'index.php?page=assistant';
        bookingLink.textContent = 'Open Booking Assistant';
        answer.append(bookingLink);
      }
      for (const item of result.items || []) {
        const url = new URL(item.url, location.href);
        if (url.origin !== location.origin) continue;
        const link = document.createElement('a');
        link.href = url.href;
        link.className = 'btn small';
        link.textContent = 'Open ' + item.reference;
        answer.append(link);
      }
      for (const item of result.availability?.items || []) {
        const line = document.createElement('p');
        line.textContent =
          item.name +
          ' — ' +
          (item.available ? 'Available' : 'Unavailable') +
          '\n' +
          item.detail +
          ' · ' +
          item.reason;
        line.className = 'header-help-resource';
        answer.append(line);
      }
      status.textContent = '';
    } catch (error) {
      status.textContent =
        error instanceof SyntaxError
          ? 'The assistant could not connect. Please try again.'
          : error.message;
      if (!form.elements.message.value) form.elements.message.value = question;
    } finally {
      thinking.remove();
      busy = false;
      send.disabled = false;
      log.scrollTop = log.scrollHeight;
    }
  }
  header.setAttribute('aria-controls', 'header-help-popup');
  header.setAttribute('aria-expanded', 'false');
  header.addEventListener('submit', (event) => {
    const text = header.elements.q.value.trim();
    if (!isQuestion(text)) return;
    event.preventDefault();
    open();
    ask(text);
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const text = form.elements.message.value.trim();
    if (text && form.reportValidity()) ask(text);
  });
})();
