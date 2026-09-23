<section class="panel tracking-chat" aria-labelledby="tracking-chat-title">
    <div class="panel-heading">
        <div>
            <h2 id="tracking-chat-title">Ask your tracking assistant</h2>
            <p>Try “Track my trip to Baler”, then “Has it started?” or “Who is assigned?”</p>
        </div>
        <?= icon(
            'users',
            24,
        ) ?>
    </div>
    <div class="tracking-chat-log" role="log" aria-live="polite" data-tracking-log>
        <p class="field-hint">
            Ask about vehicle or personnel requests. I’ll check the latest records you can access.
        </p>
        <?php
        $trackingState = $_SESSION['tracking_chat'] ?? [];
        if (($trackingState['expires'] ?? 0) >= time()) {
            foreach (
                $trackingState['messages'] ?? []
                as $turn
            ): ?>
        <article class="tracking-chat-message">
            <strong><?= $turn['role'] === 'user'
                ? 'You'
                : 'Tracking assistant' ?></strong>
            <p><?= e($turn['content']) ?></p>
        </article>
        <?php endforeach;
        }
        ?>
    </div>
    <form class="tracking-chat-form" data-tracking-chat>
        <?= csrf() ?><label for="tracking-question"
            >Your question<input
                id="tracking-question"
                name="message"
                maxlength="3000"
                required
                placeholder="Slip reference, personnel name, or a question…" /></label
        ><button class="btn primary">Ask assistant</button>
        <p data-tracking-error role="status"></p>
    </form>
</section>
<script src="assets/js/tracking-chat.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/tracking-chat.js',
) ?>" defer></script>
