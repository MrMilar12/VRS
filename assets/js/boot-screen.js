(() => {
  const boot = document.getElementById('system-boot');
  if (!boot) return;

  const minimumVisibleMs = 700;
  const startedAt = performance.now();
  let leaving = false;
  const welcomeVideo = boot.querySelector('.system-boot__video');

  if (welcomeVideo) {
    const backdrop = welcomeVideo.cloneNode(true);
    backdrop.removeAttribute('id');
    backdrop.className = 'system-boot__video-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.loop = true;
    backdrop.muted = true;
    backdrop.controls = false;
    boot.querySelector('.system-boot__content')?.prepend(backdrop);
    backdrop.play().catch(() => {});
  }

  const dismiss = () => {
    if (leaving) return;
    leaving = true;
    boot.classList.add('is-leaving');
    window.setTimeout(() => boot.remove(), 450);
  };

  const show = () => {
    leaving = false;
    boot.classList.remove('is-leaving');
    if (!boot.isConnected) document.body.prepend(boot);
    window.setTimeout(() => {
      if (!leaving) dismiss();
    }, 3500);
  };

  const dismissWhenReady = () => {
    const remaining = Math.max(0, minimumVisibleMs - (performance.now() - startedAt));
    window.setTimeout(dismiss, remaining);
  };

  // Show the brand on full page navigation, including hard refreshes.
  if (welcomeVideo) {
    welcomeVideo.addEventListener('ended', dismiss, { once: true });
    welcomeVideo.addEventListener('error', dismiss, { once: true });
    // Keep a broken or unsupported video from blocking the newly signed-in user.
    window.setTimeout(dismiss, 12000);
  } else {
    if (document.readyState === 'complete') dismissWhenReady();
    else window.addEventListener('load', dismissWhenReady, { once: true });
    window.setTimeout(dismiss, 3500);
  }

  // Prime the next same-origin document before leaving the current workspace.
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self' || link.hasAttribute('download')) return;
    const target = new URL(link.href, location.href);
    if (target.origin !== location.origin || target.href === location.href || target.hash && target.pathname === location.pathname && target.search === location.search) return;

    show();
  });

  // Cover explicit browser refresh and other native form submissions too.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.target && form.target !== '_self') return;
    show();
  }, true);
})();
