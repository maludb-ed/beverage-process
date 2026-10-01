<?php /** @var array $batch  @var array $user */
$id = (int) $batch['id']; $r = 'release-batch-row-' . $id;
$failing = (int) $batch['failing_count'];
$url = batch_release_url($id);
$names = ['abv' => 'ABV', 'co2' => 'CO2', 'free_so2' => 'Free SO2', 'ph' => 'pH'];
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-batch"><a <?= nav_attrs($url) ?>><?= status_dot($failing === 0 ? 'success' : 'danger') ?><span><?= e($batch['number']) ?></span></a> <small class="text-muted"><?= e($batch['product_name']) ?></small></td>
    <td id="<?= e($r) ?>-stage"><?= e($batch['stage_name']) ?></td>
    <td id="<?= e($r) ?>-volume"><?= fmt_qty_html($batch['current_volume_l'], 'L') ?></td>
    <td id="<?= e($r) ?>-readings">
        <?php foreach (RELEASE_QUEUE_MEASUREMENTS as $code): $reading = $batch['latest'][$code] ?? null; if ($reading === null) { continue; }
            $color = ['pass' => 'success', 'fail' => 'danger'][$reading['spec_result']] ?? 'secondary'; ?>
            <span class="me-3 text-nowrap" id="<?= e($r) ?>-reading-<?= e(str_replace('_', '-', $code)) ?>"><?= status_dot($color) ?><small class="text-muted"><?= e($names[$code]) ?></small> <?= e(number_format((float) $reading['value'], (int) $reading['decimals'])) ?></span>
        <?php endforeach; ?>
        <?php if ($batch['latest'] === []): ?><span class="text-muted">No readings</span><?php endif; ?>
    </td>
    <td id="<?= e($r) ?>-failing"><?= $failing === 0 ? badge('0', 'success') : badge((string) $failing, 'danger') ?></td>
    <td id="<?= e($r) ?>-sensory"><?= $batch['sensory_verdict'] ? badge(humanize($batch['sensory_verdict']), ['pass' => 'success', 'hold' => 'warning', 'fail' => 'danger'][$batch['sensory_verdict']] ?? 'secondary') : '<span class="text-muted">None</span>' ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end"><?php if (user_can($user, 'quality')): ?><?= nav_button($r . '-release-btn', $url, 'Release', 'feather-check-circle', 'btn btn-sm btn-primary') ?><?php endif; ?></div>
    </td>
</tr>
