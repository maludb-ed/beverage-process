<?php /** @var array $readings */ ?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-readings-table">
        <thead class="thead-light"><tr><th>Measurement</th><th>Value</th><th>Taken</th><th>Stage</th><th>Spec</th><th>Analyst</th></tr></thead>
        <tbody>
        <?php foreach ($readings as $reading): $rid = (int) $reading['id']; $r = 'batch-reading-row-' . $rid;
            $specColor = ['pass' => 'success', 'fail' => 'danger'][$reading['spec_result']] ?? 'secondary'; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-measurement"><?= e($reading['measurement_name']) ?></td>
                <td id="<?= e($r) ?>-value" class="fw-semibold"><?= e(number_format((float) $reading['value'], (int) $reading['decimals'])) ?> <small class="text-muted"><?= e($reading['unit']) ?></small></td>
                <td id="<?= e($r) ?>-taken"><?= e(format_datetime($reading['taken_at'])) ?></td>
                <td id="<?= e($r) ?>-stage"><?= e($reading['stage_name'] ?? '') ?></td>
                <td id="<?= e($r) ?>-spec"><?= status_dot($specColor) ?><?= e(humanize($reading['spec_result'])) ?><?php if ($reading['spec_id'] !== null): ?> <small class="text-muted"><?= e(batches_spec_range($reading['min_value'], $reading['max_value'])) ?></small><?php endif; ?></td>
                <td id="<?= e($r) ?>-analyst"><?= e($reading['analyst_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($readings === []): ?><tr id="batch-view-readings-empty"><td colspan="6" class="text-center text-muted py-4">No readings yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
