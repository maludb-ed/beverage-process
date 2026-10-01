<?php /** @var array $line  @var bool $canEdit  @var string $error */
$lid = (int) $line['id'];
$cid = (int) $line['count_id'];
$unit = $line['base_unit_code'];
$kind = inventory_unit_kind($line['item_class']);
$counted = $line['qty_counted_base'];
$variance = $line['variance_base'];
$error = $error ?? '';
$countedDisplay = $counted === null ? '' : round((float) to_display($counted, $unit, $kind), 3);
?>
<tr id="count-line-row-<?= e($lid) ?>">
    <td id="count-line-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small></td>
    <td id="count-line-row-<?= e($lid) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $line['lot_id']) ?>><?= status_dot(status_color($line['quality_status'])) ?><?= e($line['lot_number']) ?></a></td>
    <td id="count-line-row-<?= e($lid) ?>-expected"><?= fmt_qty_html($line['qty_expected_base'], $unit, 1, $kind) ?></td>
    <td id="count-line-row-<?= e($lid) ?>-counted-cell">
        <?php if ($canEdit): ?>
            <input type="hidden" name="line_id" value="<?= e($lid) ?>" />
            <div class="input-group input-group-sm">
                <input type="number" step="any" min="0" class="form-control<?= $error !== '' ? ' is-invalid' : '' ?>" id="count-line-row-<?= e($lid) ?>-counted" name="qty_counted" value="<?= e($countedDisplay) ?>" aria-label="Counted quantity"
                       hx-post="/counts/<?= e($cid) ?>/lines/save" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML" />
                <span class="input-group-text"><?= e(display_unit($unit, $kind)) ?></span>
            </div>
            <?php if ($error !== ''): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
        <?php else: ?>
            <?= $counted === null ? '<span class="text-muted">Not counted</span>' : fmt_qty_html($counted, $unit, 1, $kind) ?>
        <?php endif; ?>
    </td>
    <td id="count-line-row-<?= e($lid) ?>-variance" class="fw-semibold <?= $variance !== null && (float) $variance !== 0.0 ? 'text-danger' : '' ?>"><?= $variance === null ? '<span class="text-muted fw-normal">—</span>' : ((float) $variance > 0 ? '+' : '') . fmt_qty_html($variance, $unit, 1, $kind) ?></td>
    <td id="count-line-row-<?= e($lid) ?>-counted-by"><?= $line['counted_at'] ? e($line['counted_by_name'] ?? '') . ' <small class="text-muted">' . e(format_datetime($line['counted_at'])) . '</small>' : '' ?></td>
    <td id="count-line-row-<?= e($lid) ?>-note">
        <?php if ($canEdit): ?>
            <input type="text" class="form-control form-control-sm" id="count-line-row-<?= e($lid) ?>-note-input" name="note" value="<?= e($line['note'] ?? '') ?>" maxlength="200" aria-label="Note"
                   hx-post="/counts/<?= e($cid) ?>/lines/save" hx-trigger="change" hx-include="closest tr" hx-target="closest tr" hx-swap="outerHTML" />
        <?php else: ?><?= e($line['note'] ?? '') ?><?php endif; ?>
    </td>
</tr>
