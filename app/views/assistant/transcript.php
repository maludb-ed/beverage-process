<?php /** @var array $rows */ if ($rows === []): ?>
    <p class="text-muted fs-12" id="assistant-transcript-empty">Nothing yet. Try "go to lots" or "what is in the tanks".</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <div class="mb-3" id="assistant-transcript-<?= e($row['id']) ?>">
            <div class="fs-12 text-muted"><?= e(format_datetime($row['occurred_at'])) ?></div>
            <div class="fw-semibold">You: <?= e($row['details']['message'] ?? '') ?></div>
            <div><?= e($row['details']['reply'] ?? '') ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
