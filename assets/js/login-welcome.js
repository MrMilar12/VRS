(() => {
  const overlay = document.getElementById('login-welcome');
  if (!overlay) return;

  const video = overlay.querySelector('video');
  const playButton = overlay.querySelector('.login-welcome__play');
  let dismissed = false;
  let backdrop = null;
  const covered = [document.querySelector('.auth-layout'), document.getElementById('theme-toggle')].filter(Boolean);
  covered.forEach((element) => { element.inert = true; });

  if (video) {
    backdrop = video.cloneNode(true);
    backdrop.removeAttribute('id');
    backdrop.className = 'system-boot__video-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.loop = true;
    backdrop.muted = true;
    backdrop.controls = false;
    overlay.querySelector('.system-boot__content')?.prepend(backdrop);
    backdrop.play().catch(() => {});
  }

  const dismiss = () => {
    if (dismissed) return;
    dismissed = true;
    covered.forEach((element) => { element.inert = false; });
    overlay.classList.add('is-leaving');
    window.setTimeout(() => overlay.remove(), 450);
  };

  const showFallback = () => {
    if (dismissed) return;
    overlay.classList.add('is-fallback');
    if (playButton) playButton.hidden = false;
  };

  const resumeAnimation = () => {
    if (!video) return;
    video.play().then(() => {
      overlay.classList.remove('is-fallback');
      if (playButton) playButton.hidden = true;
      backdrop?.play().catch(() => {});
    }).catch(showFallback);
  };

  overlay.querySelector('.login-welcome__skip')?.addEventListener('click', dismiss);
  video?.addEventListener('ended', dismiss, { once: true });
  video?.addEventListener('error', showFallback, { once: true });
  video?.addEventListener('playing', () => {
    overlay.classList.remove('is-fallback');
    if (playButton) playButton.hidden = true;
  }, { once: true });
  playButton?.addEventListener('click', resumeAnimation);

  if (video) {
    video.muted = true;
    video.defaultMuted = true;
    video.playsInline = true;
    video.play().catch(showFallback);
  }
  // Avoid trapping the login form if the video cannot start or finish.
  window.setTimeout(dismiss, 30000);
})();
