<?php /** @var array $batch  @var array $losses  @var bool $canApprove  @var array $errors */
$id = (int) $batch['id'];
$errors = $errors ?? [];
?>
<?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'batch-view-losses-errors']) ?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-losses-table">
        <thead class="thead-light"><tr><th>Stage</th><th>Volume</th><th>Reason</th><th>TTB category</th><th>Classification</th><th>Approval</th></tr></thead>
        <tbody>
        <?php foreach ($losses as $loss): $lid = (int) $loss['id']; $r = 'batch-loss-row-' . $lid; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-stage"><?= e($loss['stage_name'] ?? '') ?><div class="fs-11 text-muted"><?= e(format_datetime($loss['occurred_at'])) ?></div></td>
                <td id="<?= e($r) ?>-qty" class="fw-semibold"><?= fmt_qty_html($loss['qty_base'], 'L', 2) ?></td>
                <td id="<?= e($r) ?>-reason"><?= e($loss['reason_name']) ?><?php if ($loss['note']): ?><div class="fs-11 text-muted"><?= e($loss['note']) ?></div><?php endif; ?></td>
                <td id="<?= e($r) ?>-ttb"><?= e(humanize($loss['ttb_category'])) ?><?= $loss['reportable'] ? '' : ' <small class="text-muted">not reported</small>' ?></td>
                <td id="<?= e($r) ?>-classification"><?= badge(humanize($loss['classification']), $loss['classification'] === 'expected' ? 'secondary' : 'warning') ?></td>
                <td id="<?= e($r) ?>-approval">
                    <?php if ($loss['approved_at'] !== null): ?>
                        <?= badge('Approved', 'success') ?> <small class="text-muted"><?= e(($loss['approved_by_name'] ?? '') . ', ' . format_date($loss['approved_at'])) ?></small>
                    <?php elseif ($loss['needs_approval']): ?>
                        <?= badge('Needs approval', 'warning') ?>
                        <?php if ($canApprove): ?>
                            <button type="button" class="btn btn-sm btn-light-brand ms-2" id="batch-view-loss-<?= e($lid) ?>-approve-btn" hx-post="/batches/<?= e($id) ?>/losses/<?= e($lid) ?>/approve" hx-target="#batch-view-pane-losses" hx-swap="innerHTML"><i class="feather-check me-1"></i>Approve</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Not required</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($losses === []): ?><tr id="batch-view-losses-empty"><td colspan="6" class="text-center text-muted py-4">No losses recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
