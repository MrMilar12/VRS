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
        <script src="assets/js/login.js" defer></script>
    </head>
    <body class="auth-page auth-refresh">
        <main class="auth-layout">
            <section class="auth-story" aria-labelledby="story-title">
                <a class="auth-brand" href="index.php" aria-label="DepEd SDO-AURORA VPRS home">
                    <img class="system-logo auth-logo" src="assets/images/vprs-logo.png" width="96" height="96" alt="" fetchpriority="high" />
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
    </body>
</html>
