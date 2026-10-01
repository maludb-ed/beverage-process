<?php /** @var array $lot  @var array $certificates  @var bool $canEdit */ $id = (int) $lot['id']; ?>
<div class="p-4">
    <?php if ($canEdit): ?><div class="mb-4 text-end"><?= nav_button('lot-view-coa-add-btn', '/lots/' . $id . '/coa/new', 'Add certificate', 'feather-file-plus', 'btn btn-sm btn-light-brand') ?></div><?php endif; ?>
    <?php foreach ($certificates as $certificate): $cid = (int) $certificate['id']; ?>
        <div class="border rounded p-3 mb-3" id="lot-certificate-<?= e($cid) ?>">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <div class="fw-semibold"><?= e($certificate['issuer'] ?: 'Certificate') ?> <small class="text-muted"><?= e(format_date($certificate['issued_on'])) ?></small></div>
                <?php if ($certificate['attachment_id']): ?>
                    <a href="/lots/<?= e($id) ?>/coa-file?attachment_id=<?= e($certificate['attachment_id']) ?>" id="lot-certificate-<?= e($cid) ?>-file" class="fs-12"><i class="feather-paperclip me-1"></i><?= e($certificate['file_name']) ?></a>
                <?php endif; ?>
            </div>
            <div class="row g-2">
                <?php foreach ($certificate['values_json'] as $value): ?>
                    <div class="col-6 col-md-3 fs-12"><span class="text-muted"><?= e(LOT_ATTRIBUTE_KEYS[$value['key']] ?? $value['key']) ?>:</span> <span class="fw-semibold"><?= e($value['value']) ?> <?= e($value['unit'] ?? '') ?></span></div>
                <?php endforeach; ?>
            </div>
            <div class="fs-11 text-muted mt-2">Recorded by <?= e($certificate['recorded_by_name']) ?>, <?= e(format_datetime($certificate['created_at'])) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if ($certificates === []): ?><p class="text-center text-muted py-4 mb-0" id="lot-view-certificates-empty">No certificate of analysis yet.</p><?php endif; ?>
</div>
