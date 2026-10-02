<?php /** @var array $removal  @var array $lines  @var ?array $tax  @var array $user  @var ?string $reverseError */
$id = (int) $removal['id'];
$status = $removal['status'];
$isReturn = $removal['direction'] === 'in';
$canEdit = user_can($user, 'compliance');
$dest = $removal['destination_kind'];
$determined = $status === 'draft' ? removal_determines_tax($removal['direction'], $dest) : (bool) $removal['tax_determined'];
$units = array_sum(array_map(static fn($l) => (int) $l['units'], $lines));
$kegLines = array_values(array_filter($lines, static fn($l) => $l['keg_id'] !== null));
$actions = '';
if ($status === 'draft' && $canEdit) {
    $confirm = 'Post ' . $removal['number'] . '? ' . $units . ($units === 1 ? ' unit (' : ' units (') . number_format((float) ($tax['total_gallons'] ?? 0), 2) . ' wine gal) '
        . ($isReturn ? 'return to bond.' : 'leave bond for ' . mb_strtolower(REMOVAL_DESTINATIONS[$dest] ?? $dest) . '.')
        . ($determined ? ' This determines $' . number_format((float) ($tax['total_tax'] ?? 0), 2) . ' excise tax.' : '');
    $actions .= nav_button('removal-view-edit-btn', '/removals/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-light-brand" id="removal-view-delete-btn" hx-post="/removals/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete draft ' . e($removal['number']) . '?"><i class="feather-trash-2 me-2"></i><span>Delete</span></button>';
    $actions .= '<button type="button" class="btn btn-primary" id="removal-view-post-btn" hx-post="/removals/' . e($id) . '/post" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="' . e($confirm) . '"><i class="feather-check-circle me-2"></i><span>Post ' . ($isReturn ? 'return' : 'removal') . '</span></button>';
}
$row = static fn(string $key, string $icon, string $label, string $html, bool $last = false): string =>
    '<li class="hstack justify-content-between ' . ($last ? 'mb-0' : 'mb-4') . '"><span class="text-muted fw-medium hstack gap-3"><i class="' . e($icon) . '"></i>' . e($label) . '</span><span id="removal-view-' . e($key) . '" class="text-end">' . $html . '</span></li>';
$taxState = static fn(?string $s): string => $s === null ? '' : ' ' . badge(humanize($s), $s === 'bonded' ? 'info' : 'warning');
?>
<?= view('shared/page-header.php', ['title' => $removal['number'], 'screen' => 'removal-view', 'crumbs' => ['Compliance' => null, 'Removals' => '/removals/', $removal['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="removal-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="removal-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($removal['number']) ?></h5><div class="fs-12 text-muted"><?= $isReturn ? 'Return (in)' : 'Removal (out)' ?></div></div>
                        <?= status_badge($status, 'removal-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <?= $row('destination', 'feather-log-out', 'Destination', e(REMOVAL_DESTINATIONS[$dest] ?? humanize($dest))) ?>
                        <?= $row('customer', 'feather-user', 'Customer', $removal['customer_id'] ? '<a ' . nav_attrs('/customers/' . (int) $removal['customer_id']) . '>' . e($removal['customer_name']) . '</a>' . ($removal['customer_permit_number'] ? ' <small class="text-muted d-block">' . e($removal['customer_permit_number']) . '</small>' : '') : '—') ?>
                        <?= $row('from', 'feather-map-pin', 'From', $removal['from_location_name'] ? e($removal['from_location_name']) . $taxState($removal['from_tax_state']) : '—') ?>
                        <?= $row('to', 'feather-navigation', 'To', $removal['to_location_name'] ? e($removal['to_location_name']) . $taxState($removal['to_tax_state']) : '—') ?>
                        <?= $row('removed-at', 'feather-clock', $isReturn ? 'Returned' : 'Removed', e(format_datetime($removal['removed_at']))) ?>
                        <?= $row('reference', 'feather-file-text', 'Reference', e($removal['reference'] ?: '—')) ?>
                        <?php if ($removal['sales_order_id'] !== null): ?>
                            <?= $row('order', 'feather-shopping-cart', 'Customer order', '<a ' . nav_attrs('/orders/' . (int) $removal['sales_order_id']) . '>' . e($removal['sales_order_number']) . '</a>') ?>
                        <?php endif; ?>
                        <?= $row('units', 'feather-package', 'Units', e($units)) ?>
                        <?= $row('gallons', 'feather-droplet', 'Wine gallons', e(number_format((float) ($removal['wine_gallons'] ?? $tax['total_gallons'] ?? 0), 4))) ?>
                        <?= $row('tax', 'feather-dollar-sign', 'Tax', $determined ? '$' . e(number_format((float) ($removal['tax_amount'] ?? $tax['total_tax'] ?? 0), 2)) . ($status === 'draft' ? ' <small class="text-muted">on posting</small>' : '') : 'Not tax determined') ?>
                        <?= $row('created-by', 'feather-user', 'Created by', e($removal['created_by_name'] ?? '')) ?>
                        <?= $row('posted', 'feather-check-circle', 'Posted', e($removal['posted_at'] ? $removal['posted_by_name'] . ', ' . format_datetime($removal['posted_at']) : 'Not yet'), $removal['reversed_by_id'] === null && $removal['reversal_of_id'] === null) ?>
                        <?php if ($removal['reversal_of_id'] !== null): ?>
                            <?= $row('reversal-of', 'feather-rotate-ccw', 'Reverses', '<a ' . nav_attrs('/removals/' . (int) $removal['reversal_of_id']) . '>' . e($removal['reversal_of_number']) . '</a>', $removal['reversed_by_id'] === null) ?>
                        <?php endif; ?>
                        <?php if ($removal['reversed_by_id'] !== null): ?>
                            <?= $row('reversed-by', 'feather-rotate-ccw', 'Reversed by', '<a ' . nav_attrs('/removals/' . (int) $removal['reversed_by_id']) . '>' . e($removal['reversed_by_number']) . '</a>', true) ?>
                        <?php endif; ?>
                    </ul>
                    <?php if ($removal['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="removal-view-notes"><?= e($removal['notes']) ?></p><?php endif; ?>
                    <?php if ($status === 'posted' && $canEdit): ?>
                        <form class="mt-4 pt-4 border-top" id="removal-view-reverse-form" hx-post="/removals/<?= e($id) ?>/reverse" hx-target="#page-content" hx-swap="innerHTML">
                            <label class="fw-semibold fs-12 mb-2" for="removal-view-reverse-reason">Reverse this <?= $isReturn ? 'return' : 'removal' ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control<?= $reverseError ? ' is-invalid' : '' ?>" name="reason" id="removal-view-reverse-reason" maxlength="500" placeholder="Reason" required />
                                <button type="submit" class="btn btn-light-brand" id="removal-view-reverse-btn" hx-confirm="Reverse <?= e($removal['number']) ?>? A <?= $isReturn ? 'removal' : 'return' ?> with the same lines posts now and changes tax state.">Reverse</button>
                            </div>
                            <?php if ($reverseError): ?><div class="invalid-feedback d-block" id="removal-view-reverse-error"><?= e($reverseError) ?></div><?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="removal-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="removal-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="removal-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#removal-view-pane-lines" role="tab">Lines</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="removal-view-tab-kegs" class="nav-link" data-bs-toggle="tab" data-bs-target="#removal-view-pane-kegs" role="tab">Kegs (<?= e(count($kegLines)) ?>)</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="removal-view-tab-tax" class="nav-link" data-bs-toggle="tab" data-bs-target="#removal-view-pane-tax" role="tab">Tax determination</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="removal-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="removal-view-lines-table">
                                <thead class="thead-light"><tr><th>Finished lot</th><th>Product</th><th>Units</th><th>Volume</th><th>Tax class</th><th>Keg</th></tr></thead>
                                <tbody>
                                <?php foreach ($lines as $line): $lid = (int) $line['id']; ?>
                                    <tr id="removal-line-row-<?= e($lid) ?>">
                                        <td id="removal-line-row-<?= e($lid) ?>-lot"><a <?= nav_attrs('/finished-lots/' . (int) $line['lot_id']) ?>><?= e($line['lot_number']) ?></a><?= $line['batch_number'] ? ' <small class="text-muted d-block">' . e($line['batch_number']) . '</small>' : '' ?></td>
                                        <td id="removal-line-row-<?= e($lid) ?>-product"><?= e($line['product_name'] ?? '') ?> <small class="text-muted d-block"><?= e($line['package_name'] ?? '') ?></small></td>
                                        <td id="removal-line-row-<?= e($lid) ?>-units"><?= e($line['units']) ?></td>
                                        <td id="removal-line-row-<?= e($lid) ?>-volume"><?= fmt_qty_html($line['volume_l'], 'L', 2) ?></td>
                                        <td id="removal-line-row-<?= e($lid) ?>-tax-class"><?= e(TAX_CLASS_LABELS[$line['tax_class']] ?? humanize($line['tax_class'])) ?></td>
                                        <td id="removal-line-row-<?= e($lid) ?>-keg"><?= e($line['keg_serial'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($lines === []): ?><tr id="removal-view-lines-empty"><td colspan="6" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="removal-view-pane-kegs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="removal-view-kegs-table">
                                <thead class="thead-light"><tr><th>Serial</th><th>Lot</th><th>Current state</th></tr></thead>
                                <tbody>
                                <?php foreach ($kegLines as $line): ?>
                                    <tr id="removal-keg-row-<?= e($line['keg_id']) ?>">
                                        <td><a <?= nav_attrs('/kegs/' . (int) $line['keg_id']) ?>><?= e($line['keg_serial']) ?></a></td>
                                        <td><?= e($line['lot_number']) ?></td>
                                        <td><?= status_badge((string) $line['keg_state']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($kegLines === []): ?><tr id="removal-view-kegs-empty"><td colspan="3" class="text-center text-muted py-4">No kegs on this <?= $isReturn ? 'return' : 'removal' ?>.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="removal-view-pane-tax" role="tabpanel">
                        <div class="card-body">
                            <?= view('removals/partials/tax-preview.php', ['tax' => $tax, 'determined' => $determined, 'destination' => $dest, 'prefix' => 'removal-view-tax', 'stored' => $status === 'draft' ? null : $removal]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
