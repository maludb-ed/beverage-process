<?php /** @var string $message  @var string $reply  @var ?string $undoId */ ?>
<div class="assistant-exchange" id="assistant-exchange-latest">
    <div class="fs-12 text-muted text-truncate" id="assistant-exchange-you">You: <?= e($message) ?></div>
    <div class="d-flex align-items-center justify-content-between gap-2">
        <div id="assistant-exchange-reply"><?= e($reply) ?></div>
        <?php if (!empty($undoId)): ?>
            <button type="button" class="btn btn-sm btn-light-brand" id="assistant-undo-btn" hx-post="/assistant/undo" hx-vals='{"undo_id": "<?= e($undoId) ?>"}' hx-target="#assistant-reply">Undo</button>
        <?php endif; ?>
    </div>
</div>
