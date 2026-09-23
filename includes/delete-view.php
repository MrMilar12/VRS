<?php
try {
    $entity = is_string($_GET['entity'] ?? null) ? $_GET['entity'] : '';
    $record = deletion_record($entity, (int) ($_GET['id'] ?? 0));
    [$label, $returnPage] = deletion_entities()[$entity];
    $problem = deletion_problem($entity, $record);
    $name =
        $record['reference'] ??
        ($record['full_name'] ??
            ($record['name'] ??
                ($record['plate'] ??
                    ($record['message'] ?? ($record['reason'] ?? '#' . $record['id'])))));
    page_heading(
        'Delete ' . $label,
        'Review the record before deleting it.',
        '<a class="btn" href="index.php?page=' . e($returnPage) . '">Back to list</a>',
    );
    ?>
<section class="panel form-panel">
    <div class="form-body stack">
        <h2><?= e(
            $name,
        ) ?></h2>
        <?php if (isset($record['status'])): ?><?= badge(
            $record['status'],
        ) ?><?php endif; ?><?php if (
            isset($record['destination'])
        ): ?>
        <p><?= nl2br(e($record['destination'])) ?></p>
        <?php endif; ?>
        <?php if ($problem !== ''): ?>
        <p class="alert" role="status"><?= e(
            $problem,
        ) ?></p>
        <?php else: ?>
        <p>This permanently deletes this <?= e(
            $label,
        ) ?>. This action cannot be undone.</p>
        <form
            method="post"
            action="actions.php"
            class="stack"
            data-confirm="Permanently delete this <?= e(
                $label,
            ) ?>?"
        >
            <?= csrf() ?><input type="hidden" name="action" value="delete_record" /><input
                type="hidden"
                name="entity"
                value="<?= e(
                    $entity,
                ) ?>"
            /><input type="hidden" name="id" value="<?= $record[
                'id'
            ] ?>" /><input
                type="hidden"
                name="return_to"
                value="index.php?page=delete&entity=<?= e(
                    $entity,
                ) ?>&id=<?= $record['id'] ?>"
            />
            <?php if (
                $entity === 'users'
            ): ?><label
                >Confirm your administrator password<input
                    type="password"
                    name="confirmation_password"
                    required
                    autocomplete="current-password" /></label
            ><?php endif; ?><label class="checkbox-label"
                ><input type="checkbox" name="confirm_delete" value="1" required /> I confirm
                deletion of this <?= e(
                    $label,
                ) ?>.</label
            >
            <div class="form-actions">
                <a class="btn" href="index.php?page=<?= e(
                    $returnPage,
                ) ?>">Cancel</a
                ><button type="submit" class="btn danger">Delete <?= e(
                    $label,
                ) ?></button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php
} catch (RuntimeException $e) {
    echo '<div class="alert error">' . e($e->getMessage()) . '</div>';
}
