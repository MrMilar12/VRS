<?php require_once __DIR__ . '/assistant-welcome.php'; ?>
<section
    class="panel dashboard-briefing"
    aria-label="Your daily briefing"
    data-dashboard-briefing
    data-timezone="<?= e(
        $config['timezone'],
    ) ?>"
    data-server-time="<?= time() * 1000 ?>"
>
    <div>
        <span class="eyebrow">YOUR DAILY BRIEFING</span>
        <h2>Today at a glance</h2>
        <time data-briefing-clock datetime="<?= date(
             'c',
         ) ?>"><?= e(date('l, F j, Y · g:i A') . ' · ' . $config['timezone']) ?></time>
    </div>
    <div class="briefing-weather">
        <h3>Weather near you</h3>
        <p data-briefing-weather role="status">
            Detecting your location. Allow location access for local weather.
        </p>
        <button class="btn small" type="button" data-briefing-refresh>Refresh weather</button>
        <a
            class="text-link"
            href="https://open-meteo.com/"
            target="_blank"
            rel="noopener noreferrer"
            >Weather by Open-Meteo</a
        >
    </div>
    <div class="briefing-summary">
        <h3>System summary</h3>
        <p><?= nl2br(
             e(assistant_welcome_summary($rows, $personnelRows, $now)),
         ) ?></p>
        <small>Based on records you can access · Updated <?= e(
            date('g:i A'),
        ) ?></small>
        <div class="heading-actions">
            <a class="btn small" href="index.php?page=requisitions">View requisitions</a
            ><?php if (
                manage()
            ): ?><a class="btn small" href="index.php?page=reports"
                >Reports &amp; insights</a
            ><?php endif; ?>
        </div>
    </div>
    <form data-briefing-token hidden><?= csrf() ?></form>
</section>
<script src="assets/js/dashboard-welcome.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/dashboard-welcome.js',
) ?>" defer></script>
