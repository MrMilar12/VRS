<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#f6f4f0" />
        <link rel="icon" type="image/png" sizes="64x64" href="assets/images/vprs-favicon.png" />
        <title>Sign in · VPRS</title>
        <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(
            __DIR__ . '/../assets/css/style.css',
        ) ?>" />
        <link rel="stylesheet" href="assets/css/login.css?v=<?= filemtime(
            __DIR__ . '/../assets/css/login.css',
        ) ?>" />
        <link rel="stylesheet" href="assets/css/login-refresh.css?v=<?= filemtime(__DIR__ . '/../assets/css/login-refresh.css') ?>" />
        <link rel="stylesheet" href="assets/css/boot-screen.css?v=<?= filemtime(__DIR__ . '/../assets/css/boot-screen.css') ?>" />
        <script src="assets/js/login.js" defer></script>
        <?php if ($showLoginWelcome): ?>
        <script src="assets/js/login-welcome.js?v=<?= filemtime(__DIR__ . '/../assets/js/login-welcome.js') ?>" defer></script>
        <?php endif; ?>
    </head>
    <body class="auth-page auth-refresh">
        <?php if ($showLoginWelcome): ?>
        <div class="system-boot system-boot--welcome login-welcome" id="login-welcome" aria-label="Welcome to VPRS">
            <div class="system-boot__content">
                <img class="login-welcome__fallback" src="assets/images/vprslogo.png" width="260" height="260" alt="VPRS logo" />
                <video class="system-boot__video" autoplay muted playsinline preload="auto" aria-label="VPRS welcome animation">
                    <source src="assets/videos/vprs-welcome.mp4" type="video/mp4" />
                </video>
                <div class="system-boot__welcome-copy">
                    <span>Welcome to</span>
                    <strong>VPRS</strong>
                    <small>DepEd SDO-AURORA</small>
                </div>
                <button class="login-welcome__play" type="button" hidden>Play welcome animation</button>
                <button class="login-welcome__skip" type="button">Skip</button>
            </div>
        </div>
        <?php endif; ?>
        <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
            <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <main class="auth-layout">
            <section class="auth-story" aria-labelledby="story-title">
                <a class="auth-brand" href="index.php" aria-label="DepEd SDO-AURORA VPRS home">
                    <img class="system-logo auth-logo" src="assets/images/vprslogo.png" width="96" height="96" alt="" fetchpriority="high" />
                    <span>VPRS<span class="auth-brand-caption">VEHICLE &amp; PERSONNEL</span><span class="brand-organization">DepEd SDO-AURORA</span></span>
                </a>
                <div class="auth-story-copy">
                    <span class="auth-kicker">DEPARTMENT OF EDUCATION · AURORA</span>
                    <h1 id="story-title">Making room for<br /><em>what matters.</em></h1>
                    <p>Less paperwork. More purpose.<br />Your people and journeys, together in one workspace.</p>
                </div>
                <div class="auth-services" aria-label="Workspace services">
                    <div><span><?= icon('car', 22) ?></span><div><strong>Vehicle requisitions</strong><p>Keep every journey organized.</p></div></div>
                    <div><span><?= icon('users', 22) ?></span><div><strong>Personnel requests</strong><p>Bring the right people together.</p></div></div>
                    <div><span><?= icon('check', 22) ?></span><div><strong>Clear approvals</strong><p>Follow each request from start to finish.</p></div></div>
                </div>
                <div class="auth-story-footer"><span>DepEd SDO-AURORA</span><span>People. Purpose. Progress.</span></div>
            </section>
            <section class="auth-entry" aria-labelledby="login-title">
                <div class="auth-entry-top">
                    <span><?= icon(
                        'shield',
                        14,
                    ) ?> Your DepEd workspace</span
                    ><span class="auth-edition">SDO-AURORA</span>
                </div>
                <div class="auth-form-wrap">
                    <!-- Duplicate branding for mobile view -->
                    <div class="auth-brand mobile-only">
                        <img class="system-logo auth-logo" src="assets/images/vprslogo.png" width="80" height="80" alt="" />
                        <span>VPRS<span class="auth-brand-caption">VEHICLE &amp; PERSONNEL</span><span class="brand-organization">DepEd SDO-AURORA</span></span>
                    </div>
                    <div class="auth-story-copy mobile-only" style="text-align: center; margin-bottom: 30px;">
                        <h1 style="font-size: 24px; margin: 10px 0;">Making room for <em style="color: var(--auth-accent-soft);">what matters.</em></h1>
                    </div>

                    <div class="auth-office"><?= icon('office', 16) ?><span><?= e($organization) ?></span></div>
                    <h2 id="login-title">Welcome back.</h2>
                    <p class="auth-description">Welcome to your workspace. Sign in to get started.</p>
                    <?php if ($error): ?>
                    <div class="auth-error" role="alert">
                        <?= icon('shield', 18) ?><span><?= e(
                            $error,
                        ) ?></span>
                    </div>
                    <?php endif; ?>
                    <form method="post" class="auth-form">
                        <?= csrf() ?><label for="login-email">Email address</label>
                        <div class="auth-input">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                aria-hidden="true"
                            >
                                <rect x="3" y="5" width="18" height="14" rx="3" />
                                <path d="m4 7 8 6 8-6" /></svg
                            ><input
                                id="login-email"
                                name="email"
                                type="email"
                                required
                                autocomplete="username"
                                value="<?= e(
                                    is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
                                ) ?>"
                                placeholder="you@your-office.gov.ph"
                                spellcheck="false"
                                autocapitalize="none"
                            />
                        </div>
                        <label for="login-password">Password</label>
                        <div class="auth-input">
                            <?= icon(
                                'shield',
                                18,
                            ) ?><input
                                id="login-password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            /><button
                                type="button"
                                class="password-toggle"
                                aria-controls="login-password"
                                aria-label="Show password"
                                aria-pressed="false"
                                hidden
                            >
                                Show
                            </button>
                        </div>
                        <button class="auth-submit" type="submit">
                            <span>Sign in to workspace</span>
                        </button>
                    </form>
                    <p class="auth-help">Manage authenticator verification in your profile.</p>
                    <p class="auth-signup">
                        New here? <a href="register.php">Create an account</a>
                    </p>
                </div>
                <footer class="auth-entry-footer">
                    <span>Vehicle &amp; Personnel Requisition System</span
                    ><span>People and journeys, coordinated.</span>
                </footer>
            </section>
        </main>
        <script>
            const toggle = document.getElementById('theme-toggle');
            const body = document.body;

            toggle.addEventListener('click', () => {
                body.classList.toggle('dark-mode');
                const isDark = body.classList.contains('dark-mode');
                localStorage.setItem('auth-theme', isDark ? 'dark' : 'light');
            });

            // Load preference
            if (localStorage.getItem('auth-theme') === 'dark' || 
                (!localStorage.getItem('auth-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                body.classList.add('dark-mode');
            }
        </script>
    </body>
</html>
