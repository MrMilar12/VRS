(() => {
  const overlay = document.getElementById('login-welcome');
  if (!overlay) return;

  const video = overlay.querySelector('video');
  const playButton = overlay.querySelector('.login-welcome__play');
  let dismissed = false;
  const covered = [document.querySelector('.auth-layout'), document.getElementById('theme-toggle')].filter(Boolean);
  covered.forEach((element) => { element.inert = true; });

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
  // Keep the welcome brief so the sign-in form becomes available quickly.
  window.setTimeout(dismiss, 5200);
})();
