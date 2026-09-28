<?php
function public_auth_start(string $title, string $description): void
{
    ?><!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#203e32" />
        <link rel="icon" type="image/png" sizes="64x64" href="assets/images/vprs-favicon.png" />
        <title><?= e(
            $title,
        ) ?> · VPRS</title>
        <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" />
        <link rel="stylesheet" href="assets/css/login.css?v=<?= filemtime(__DIR__ . '/../assets/css/login.css') ?>" />
    </head>
    <body class="auth-page">
        <main class="auth-public">
            <a class="auth-public-brand" href="login.php" aria-label="DepEd SDO-AURORA VPRS sign in">
                <img class="system-logo" src="assets/images/vprs-logo.png" width="72" height="72" alt="" />
                <div>VPRS<span class="auth-brand-caption">VEHICLE &amp; PERSONNEL</span><span class="brand-organization">DepEd SDO-AURORA</span></div>
            </a>
            <section class="auth-public-card">
                <h1><?= e($title) ?></h1>
                <p class="auth-description"><?= e($description) ?></p>
                <?php
                }
                function public_auth_end(): void
                {
                    ?>
                <p class="auth-help"><a href="login.php">Back to sign in</a></p>
            </section>
            <footer class="auth-help">Vehicle &amp; Personnel Requisition System</footer>
        </main>
    </body>
</html>
<?php
}
