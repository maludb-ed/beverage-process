<?php
/** @var array $import  @var ?array $result  @var ?string $readError  @var array $orders  @var bool $canPrice */
$id = (int) $import['id'];
$status = $import['status'];
$s = 'order-import-view';
$actions = '';
if ($status === 'imported') {
    $actions .= '<button type="button" class="btn btn-light-brand" id="' . $s . '-undo-btn" hx-post="/orders/import/' . $id . '/undo" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Undo ' . e($import['number']) . '? Its ' . (int) $import['orders_created'] . ' orders are deleted; customers it created stay."><i class="feather-rotate-ccw me-2"></i><span>Undo import</span></button>';
}
if ($status === 'previewed') {
    $actions .= '<button type="button" class="btn btn-light-brand" id="' . $s . '-discard-btn" hx-post="/orders/import/' . $id . '/discard" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Discard ' . e($import['number']) . ' without importing?"><i class="feather-x-circle me-2"></i><span>Discard</span></button>';
}
$actions .= nav_button($s . '-new-btn', '/orders/import', 'New import', 'feather-upload', 'btn btn-light-brand');
$counts = $result['counts'] ?? null;
$rowBadge = ['ok' => ['Ready', 'success'], 'skip' => ['Skipped', 'secondary'], 'error' => ['Error', 'danger']];
$headerOptions = ['' => 'Not in the file'] + array_combine($import['headers'] ?? [], $import['headers'] ?? []);
?>
<?= view('shared/page-header.php', ['title' => $import['number'], 'screen' => $s, 'crumbs' => ['Sales' => null, 'Import orders' => '/orders/import', $import['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="<?= $s ?>-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($import['number']) ?></h5>
                        <?= badge(ORDER_IMPORT_STATUSES[$status] ?? humanize($status), ORDER_IMPORT_STATUS_COLORS[$status] ?? 'secondary', $s . '-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-file"></i>File</span><span id="<?= $s ?>-file" class="text-end text-break"><?= e($import['file_name']) ?></span></li>
                        <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-list"></i>Rows</span><span id="<?= $s ?>-rows"><?= e(number_format((int) $import['rows_total'])) ?></span></li>
                        <?php if ($counts !== null): ?>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-shopping-cart"></i>Orders to create</span><span id="<?= $s ?>-orders" class="text-end"><?= e(number_format($counts['orders'])) ?> <small class="text-muted d-block"><?= e($counts['history'] . ' history, ' . $counts['confirmed'] . ' confirmed, ' . $counts['draft'] . ' draft; ' . $counts['lines'] . ' lines') ?></small></span></li>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check"></i>Rows ready / skipped</span><span id="<?= $s ?>-ready"><?= e($counts['ok'] . ' / ' . $counts['skipped']) ?></span></li>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-alert-triangle"></i>Rows with errors</span><span id="<?= $s ?>-errors" class="<?= $counts['errors'] ? 'text-danger fw-semibold' : '' ?>"><?= e($counts['errors']) ?></span></li>
                            <li class="hstack justify-content-between gap-3 mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user-plus"></i>New customers</span><span id="<?= $s ?>-new-customers" class="text-end"><?= $result['new_customers'] === [] ? 'None' : e(implode(', ', $result['new_customers'])) ?></span></li>
                        <?php else: ?>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-shopping-cart"></i>Orders created</span><span id="<?= $s ?>-orders"><?= e(number_format((int) $import['orders_created'])) ?></span></li>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check"></i>Rows imported / skipped / failed</span><span id="<?= $s ?>-ready"><?= e($import['rows_imported'] . ' / ' . $import['rows_skipped'] . ' / ' . $import['rows_failed']) ?></span></li>
                            <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user-plus"></i>Customers created</span><span id="<?= $s ?>-customers-created"><?= e($import['customers_created']) ?></span></li>
                            <li class="hstack justify-content-between gap-3 mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i><?= $status === 'undone' ? 'Undone' : 'Imported' ?></span><span id="<?= $s ?>-imported" class="text-end"><?= e($status === 'undone' ? ($import['undone_by_name'] . ', ' . format_datetime($import['undone_at'])) : (($import['imported_by_name'] ?? '') . ', ' . format_datetime($import['imported_at']))) ?></span></li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($readError !== null): ?><div class="alert alert-danger mt-4 mb-0" id="<?= $s ?>-read-error"><?= e($readError) ?></div><?php endif; ?>
                    <?php if ($status === 'previewed' && $counts !== null && $counts['orders'] > 0): ?>
                        <form class="mt-4 pt-4 border-top" id="<?= $s ?>-commit-form" hx-post="/orders/import/<?= e($id) ?>/commit" hx-target="#page-content" hx-swap="innerHTML">
                            <?php if ($result['new_customers'] !== []): ?>
                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="create_customers" value="1" id="<?= $s ?>-create-customers" checked /><label class="form-check-label fs-12" for="<?= $s ?>-create-customers">Create the <?= e(count($result['new_customers'])) ?> new customers</label></div>
                            <?php endif; ?>
                            <?php if ($counts['errors'] > 0): ?>
                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="skip_errors" value="1" id="<?= $s ?>-skip-errors" /><label class="form-check-label fs-12" for="<?= $s ?>-skip-errors">Skip the <?= e($counts['errors']) ?> rows with errors</label></div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary w-100 mt-2" id="<?= $s ?>-commit-btn" hx-confirm="Import <?= e($counts['orders']) ?> orders from <?= e($import['file_name']) ?>?"><i class="feather-check-circle me-2"></i>Import <?= e(number_format($counts['orders'])) ?> orders</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($status === 'previewed' && $readError === null): ?>
            <form class="card" id="<?= $s ?>-mapping-form" hx-post="/orders/import/<?= e($id) ?>/map" hx-target="#page-content" hx-swap="innerHTML">
                <div class="card-header"><h5 class="card-title">Columns</h5><button type="submit" class="btn btn-sm btn-light-brand" id="<?= $s ?>-mapping-apply-btn"><i class="feather-refresh-cw me-1"></i>Apply</button></div>
                <div class="card-body">
                    <?php foreach (ORDER_IMPORT_FIELDS as $field => [$label, $required]): ?>
                        <div class="mb-3">
                            <label class="fw-semibold fs-12" for="<?= $s ?>-mapping-<?= e(str_replace('_', '-', $field)) ?>"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
                            <select class="form-select" name="mapping[<?= e($field) ?>]" id="<?= $s ?>-mapping-<?= e(str_replace('_', '-', $field)) ?>">
                                <?php foreach ($headerOptions as $value => $text): ?><option value="<?= e($value) ?>"<?= ($import['mapping'][$field] ?? '') === (string) $value ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <label class="fw-semibold fs-12" for="<?= $s ?>-future-status">Upcoming orders import as</label>
                    <select class="form-select" name="future_status" id="<?= $s ?>-future-status">
                        <option value="confirmed"<?= $import['future_status'] === 'confirmed' ? ' selected' : '' ?>>Confirmed</option>
                        <option value="draft"<?= $import['future_status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                    </select>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <?php if ($result !== null): ?>
            <div class="card" id="<?= $s ?>-rows-card">
                <div class="card-header"><h5 class="card-title">Preview</h5><span class="fs-12 text-muted"><?= count($result['rows']) > 300 ? 'First 300 rows' : 'Every row' ?>; errors first</span></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= $s ?>-rows-table">
                        <thead class="thead-light"><tr><th>Row</th><th>Customer</th><th>Reference</th><th>Due</th><th>Format</th><th>Units</th><?php if ($canPrice): ?><th>Price</th><?php endif; ?><th>Result</th></tr></thead>
                        <tbody>
                        <?php $sorted = $result['rows']; uasort($sorted, static fn($a, $b) => ($a['result'] === 'error' ? 0 : 1) <=> ($b['result'] === 'error' ? 0 : 1));
                        foreach (array_slice($sorted, 0, 300, true) as $n => $row): [$label, $color] = $rowBadge[$row['result']]; $v = $row['values']; ?>
                            <tr id="<?= $s ?>-row-<?= e($n) ?>">
                                <td><?= e($n) ?></td>
                                <td><?= e($v['customer'] ?? '') ?></td>
                                <td><?= e($v['reference'] ?? '') ?></td>
                                <td><?= e($v['due_on'] ?? '') ?></td>
                                <td><?= e(trim(($v['product'] ?? '') . ' ' . ($v['format'] ?? ''))) ?></td>
                                <td><?= e($v['units'] ?? '') ?></td>
                                <?php if ($canPrice): ?><td><?= e($v['unit_price'] ?? '') ?></td><?php endif; ?>
                                <td><?= badge($label, $color) ?> <small class="<?= $row['result'] === 'error' ? 'text-danger' : 'text-muted' ?>"><?= e($row['message']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($status !== 'previewed'): ?>
            <?php if ($import['errors'] !== []): ?>
                <div class="card" id="<?= $s ?>-failed-card">
                    <div class="card-header"><h5 class="card-title">Rows not imported</h5></div>
                    <div class="table-responsive"><table class="table mb-0" id="<?= $s ?>-failed-table"><thead class="thead-light"><tr><th>Row</th><th>Reason</th></tr></thead><tbody>
                        <?php foreach (array_slice($import['errors'], 0, 300) as $err): ?><tr><td><?= e($err['row']) ?></td><td class="text-danger"><?= e($err['message']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            <?php endif; ?>
            <div class="card" id="<?= $s ?>-orders-card">
                <div class="card-header"><h5 class="card-title">Orders</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= $s ?>-orders-table">
                        <thead class="thead-light"><tr><th>Order</th><th>Customer</th><th>Reference</th><th>Due</th><th>Units</th><?php if ($canPrice): ?><th class="text-end">Value</th><?php endif; ?><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $o): $oid = (int) $o['id']; ?>
                            <tr id="<?= $s ?>-order-<?= e($oid) ?>">
                                <td><a <?= nav_attrs('/orders/' . $oid) ?>><?= status_dot(order_status_color($o['status'])) ?><?= e($o['number']) ?></a></td>
                                <td><?= e($o['customer_name']) ?></td>
                                <td><?= e($o['customer_reference']) ?></td>
                                <td><?= e(format_date($o['requested_on'])) ?></td>
                                <td><?= e(number_format((int) $o['units'])) ?></td>
                                <?php if ($canPrice): ?><td class="text-end"><?= $o['order_value'] !== null ? '$' . e(number_format((float) $o['order_value'], 2)) : '—' ?></td><?php endif; ?>
                                <td><?= order_status_badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($orders === []): ?><tr><td colspan="<?= $canPrice ? 7 : 6 ?>" class="text-center text-muted py-4"><?= $status === 'undone' ? 'This import was undone; its orders were deleted.' : 'No orders.' ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
