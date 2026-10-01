<?php /** @var array $rows  this user's recent assistant exchanges, newest first */ if ($rows === []): ?>
    <p class="text-muted fs-12" id="assistant-transcript-empty">Nothing yet. Try "go to lots" or "what is in the tanks".</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <?php $d = $row['details']; $ama = ($d['surface'] ?? '') === 'ama' || $row['action'] === 'ama_question'; ?>
        <div class="mb-3 pb-3 border-bottom" id="assistant-transcript-<?= e($row['id']) ?>">
            <div class="fs-11 text-muted">
                <?= e(format_datetime($row['occurred_at'])) ?>
                <span class="badge <?= $ama ? 'bg-soft-primary text-primary' : 'bg-soft-secondary text-secondary' ?> ms-1"><?= $ama ? 'Ask me anything' : 'Command bar' ?></span>
                <?php if (!empty($d['screen']) && !$ama): ?><span class="ms-1">on <?= e($d['screen']) ?></span><?php endif; ?>
            </div>
            <div class="fw-semibold text-break">You: <?= e($d['message'] ?? '') ?></div>
            <div class="text-break"><?= e(mb_strimwidth((string) ($d['reply'] ?? ''), 0, 400, '…')) ?></div>
            <?php if (!empty($d['navigate'])): ?><div class="fs-11 text-muted"><i class="feather-corner-down-right me-1"></i><?= e($d['navigate']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <a class="fs-12" id="assistant-transcript-ama-link" <?= nav_attrs('/ama/') ?> data-bs-dismiss="offcanvas">Open the full conversation</a>
<?php endif; ?>
