<?php
/**
 * One exchange in the AMA thread: the question bubble, the answer, and its source note.
 * Used for the live answer (appended to #ama-thread) and for history rows.
 * @var string $message  @var string $reply
 * @var ?string $undoId  @var ?string $pending  @var ?array $navigate  @var array $sources
 * @var ?int $activityId  @var string $surface  @var ?string $occurredAt  @var ?array $context
 */
require_once __DIR__ . '/markdown.php';
$undoId     = $undoId ?? null;
$pending    = $pending ?? null;
$navigate   = $navigate ?? null;
$sources    = $sources ?? [];
$surface    = $surface ?? 'ama';
$occurredAt = $occurredAt ?? null;
$key        = isset($activityId) && $activityId ? (string) $activityId : 'new-' . bin2hex(random_bytes(4));
[$answer, $sourceNote] = ama_split_source((string) $reply, $sources);
?>
<div class="ama-exchange mb-4" id="ama-exchange-<?= e($key) ?>">
    <div class="d-flex justify-content-end mb-2">
        <div class="bg-soft-primary text-dark rounded-3 px-3 py-2 col-11 col-md-9 text-break" id="ama-exchange-<?= e($key) ?>-question">
            <?php if ($surface === 'command_bar'): ?><span class="badge bg-soft-secondary text-secondary me-1" title="Asked from the command bar"><i class="feather-message-circle"></i></span><?php endif; ?>
            <?= e($message) ?>
        </div>
    </div>
    <div class="d-flex">
        <div class="border rounded-3 px-3 py-2 col-12 col-md-10 text-break" id="ama-exchange-<?= e($key) ?>-answer">
            <?= ama_markdown($answer) ?>
            <?php if ($navigate !== null): ?>
                <a class="btn btn-sm btn-light-brand mb-2" id="ama-exchange-<?= e($key) ?>-open" <?= nav_attrs($navigate['path']) ?>><i class="feather-arrow-right me-1"></i>Open <?= e($navigate['path']) ?></a>
            <?php endif; ?>
            <?php if ($pending !== null): ?>
                <div class="alert alert-soft-danger-message p-2 mb-2 fs-12" id="ama-exchange-<?= e($key) ?>-confirm-summary"><?= e($pending) ?></div>
                <button type="button" class="btn btn-sm btn-danger mb-2" id="ama-exchange-<?= e($key) ?>-confirm-btn" hx-post="/assistant/message" hx-target="#ama-thread" hx-swap="beforeend" hx-disabled-elt="this"
                        hx-vals='<?= e(json_encode(['message' => $message, 'confirmed' => '1', 'source' => 'ama', 'screen' => 'ama'])) ?>'>Confirm</button>
            <?php endif; ?>
            <?php if (!empty($undoId)): ?>
                <button type="button" class="btn btn-sm btn-light-brand mb-2" id="ama-exchange-<?= e($key) ?>-undo-btn" hx-post="/assistant/undo" hx-vals='<?= e(json_encode(['undo_id' => $undoId])) ?>' hx-target="#assistant-reply"
                        hx-on::after-request="document.getElementById('assistant-reply').hidden=false;">Undo</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="fs-11 text-muted mt-1" id="ama-exchange-<?= e($key) ?>-meta">
        <?php if ($sourceNote !== null): ?><i class="feather-database me-1"></i><?= e($sourceNote) ?><?php endif; ?>
        <?php if ($occurredAt): ?><span class="ms-2"><?= e(format_datetime($occurredAt)) ?></span><?php endif; ?>
    </div>
</div>
