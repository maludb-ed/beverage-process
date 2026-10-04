<?php
/**
 * The latest command-bar exchange, swapped into #assistant-reply.
 * @var string $message  @var string $reply  @var ?string $undoId
 * @var ?string $pending   needs_confirmation summary (renders Confirm / Never mind)
 * @var ?array  $navigate  ['path' => ...] when the reply came with HX-Location
 * @var ?array  $context   screen context to re-send with Confirm
 */
$pending  = $pending ?? null;
$navigate = $navigate ?? null;
$context  = $context ?? [];
$actions  = $actions ?? [];
?>
<div class="assistant-exchange" id="assistant-exchange-latest">
    <div class="fs-12 text-muted text-truncate" id="assistant-exchange-you">You: <?= e($message) ?></div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div id="assistant-exchange-reply"><?= e($reply) ?><?php if ($navigate !== null): ?> <i class="feather-corner-down-right text-muted" title="<?= e($navigate['path']) ?>"></i><?php endif; ?></div>
        <?php if (!empty($undoId)): ?>
            <button type="button" class="btn btn-sm btn-light-brand" id="assistant-undo-btn" hx-post="/assistant/undo" hx-vals='<?= e(json_encode(['undo_id' => $undoId])) ?>' hx-target="#assistant-reply">Undo</button>
        <?php endif; ?>
        <?php if ($pending !== null): ?>
            <div class="d-flex gap-2" id="assistant-confirm-actions">
                <button type="button" class="btn btn-sm btn-danger" id="assistant-confirm-btn" hx-post="/assistant/message" hx-target="#assistant-reply" hx-swap="innerHTML" hx-disabled-elt="this"
                        hx-vals='<?= e(json_encode(['message' => $message, 'confirmed' => '1', 'screen' => (string) ($context['screen'] ?? ''), 'entity' => (string) ($context['entity'] ?? ''), 'record_id' => (string) ($context['record_id'] ?? '')])) ?>'>Confirm</button>
                <button type="button" class="btn btn-sm btn-light" id="assistant-cancel-btn" onclick="document.getElementById('assistant-reply').hidden=true;document.getElementById('assistant-input').focus();">Never mind</button>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($pending !== null): ?>
        <div class="fs-12 text-danger mt-1" id="assistant-confirm-summary"><?= e($pending) ?></div>
    <?php endif; ?>
    <?php if (!empty($actions)): ?>
        <div class="fs-12 text-muted mt-1" id="assistant-exchange-actions"><?php foreach ($actions as $i => $a): ?><?= $i > 0 ? ' · ' : '' ?><span id="assistant-exchange-action-<?= $i ?>"><?= e((string) ($a['tool'] ?? '')) ?> <?= in_array($a['status'] ?? '', ['ok', 'success'], true) ? '✓' : (($a['status'] ?? '') === 'awaiting_approval' ? '⏸ awaiting approval' : '✗') ?></span><?php endforeach; ?></div>
    <?php endif; ?>
</div>
