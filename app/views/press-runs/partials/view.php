<?php /** @var array $run  @var array $inputs  @var array $outputs  @var array $user */
$id = (int) $run['id'];
$status = $run['status'];
$isDraft = $status === 'draft';
$fruitKg = $isDraft ? array_sum(array_map(static fn($i) => (float) $i['qty_kg'], $inputs)) : (float) $run['fruit_kg_total'];
$juiceL = $isDraft ? array_sum(array_map(static fn($o) => $o['kind'] === 'juice' ? (float) $o['qty_base'] : 0.0, $outputs)) : (float) $run['juice_l_total'];
$pomaceKg = $isDraft ? array_sum(array_map(static fn($o) => $o['kind'] === 'pomace' ? (float) $o['qty_base'] : 0.0, $outputs)) : (float) $run['pomace_kg_total'];
$yield = batches_press_yield($isDraft ? ($fruitKg > 0 ? $juiceL / $fruitKg : null) : $run['yield_l_per_kg']);
$actions = '';
if ($isDraft && user_can($user, 'production')) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="press-run-view-delete-btn" hx-post="/press-runs/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete draft press run ' . e($run['number']) . '?"><i class="feather-trash-2 me-2"></i><span>Delete</span></button>';
    $actions .= nav_button('press-run-view-edit-btn', '/press-runs/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-primary" id="press-run-view-post-btn" hx-post="/press-runs/' . e($id) . '/post" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Post press run</span></button>';
}
$li = static fn(string $key, string $icon, string $label, string $html, bool $last = false) => '<li class="hstack justify-content-between ' . ($last ? 'mb-0' : 'mb-4') . '"><span class="text-muted fw-medium hstack gap-3"><i class="' . e($icon) . '"></i>' . e($label) . '</span><span id="press-run-view-' . e($key) . '" class="text-end">' . $html . '</span></li>';
?>
<?php $actions = nav_button('press-run-view-schedule-btn', '/schedule/?subject=press_run:' . (int) $run['id'], 'Schedule', 'feather-calendar', 'btn btn-light-brand') . (isset($user) && user_can($user, 'production') ? nav_button('press-run-view-reserve-btn', '/reservations/new?subject_kind=press_run&subject_id=' . (int) $run['id'], 'Reserve', 'feather-bookmark', 'btn btn-light-brand') : '') . ($actions ?? ''); ?>
<?= view('shared/page-header.php', ['title' => $run['number'], 'screen' => 'press-run-view', 'crumbs' => ['Production' => null, 'Press runs' => '/press-runs/', $run['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="press-run-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="press-run-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($run['number']) ?></h5>
                        <?= status_badge($status, 'press-run-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <?= $li('run-on', 'feather-calendar', 'Date', e(format_date($run['run_on']))) ?>
                        <?= $li('press', 'feather-settings', 'Press', e($run['press_name'] ?? '—')) ?>
                        <?= $li('fruit', 'feather-shopping-bag', 'Fruit', fmt_qty_html($fruitKg, 'kg', 1, 'fruit')) ?>
                        <?= $li('juice', 'feather-droplet', 'Juice', fmt_qty_html($juiceL, 'L', 1)) ?>
                        <?= $li('pomace', 'feather-trash', 'Pomace', fmt_qty_html($pomaceKg, 'kg', 1)) ?>
                        <?= $li('yield', 'feather-trending-up', 'Yield', $yield['gal_per_ton'] === null ? '—' : e(number_format($yield['gal_per_ton'], 0)) . ' gal/ton <small class="text-muted">' . e(number_format($yield['gal_per_bushel'], 2)) . ' gal/bu</small>' . ($isDraft ? ' <small class="text-muted">(planned)</small>' : '')) ?>
                        <?= $li('posted', 'feather-check-circle', 'Posted', e($run['posted_at'] ? $run['posted_by_name'] . ', ' . format_datetime($run['posted_at']) : 'Not yet'), true) ?>
                    </ul>
                    <?php if ($run['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="press-run-view-notes"><?= e($run['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="press-run-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="press-run-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="press-run-view-tab-inputs" class="nav-link active" data-bs-toggle="tab" data-bs-target="#press-run-view-pane-inputs" role="tab">Fruit in</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="press-run-view-tab-outputs" class="nav-link" data-bs-toggle="tab" data-bs-target="#press-run-view-pane-outputs" role="tab">Juice and pomace out</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="press-run-view-pane-inputs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="press-run-view-inputs">
                                <thead class="thead-light"><tr><th>Lot</th><th>Item</th><th>Variety</th><th>Weight</th></tr></thead>
                                <tbody>
                                <?php foreach ($inputs as $input): $iid = (int) $input['id']; ?>
                                    <tr id="press-run-input-row-<?= e($iid) ?>">
                                        <td id="press-run-input-row-<?= e($iid) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $input['lot_id']) ?>><?= status_dot(status_color($input['quality_status'])) ?><?= e($input['lot_number']) ?></a></td>
                                        <td id="press-run-input-row-<?= e($iid) ?>-item"><?= e($input['item_code']) ?> <small class="text-muted"><?= e($input['item_name']) ?></small></td>
                                        <td id="press-run-input-row-<?= e($iid) ?>-variety"><?= e($input['variety'] ?? '') ?></td>
                                        <td id="press-run-input-row-<?= e($iid) ?>-qty" class="fw-semibold"><?= fmt_qty_html($input['qty_kg'], 'kg', 1, 'fruit') ?> <small class="text-muted"><?= e(format_qty($input['qty_kg'], 1)) ?> kg</small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($inputs === []): ?><tr id="press-run-view-inputs-empty"><td colspan="4" class="text-center text-muted py-4">No fruit lots yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="press-run-view-pane-outputs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="press-run-view-outputs">
                                <thead class="thead-light"><tr><th>Kind</th><th>Item</th><th>Lot</th><th>Quantity</th><th>Brix</th><th>Vessel or location</th></tr></thead>
                                <tbody>
                                <?php foreach ($outputs as $output): $oid = (int) $output['id']; ?>
                                    <tr id="press-run-output-row-<?= e($oid) ?>">
                                        <td id="press-run-output-row-<?= e($oid) ?>-kind"><?= badge(PRESS_RUN_OUTPUT_KINDS[$output['kind']], $output['kind'] === 'juice' ? 'info' : 'secondary') ?></td>
                                        <td id="press-run-output-row-<?= e($oid) ?>-item"><?= e($output['item_code']) ?> <small class="text-muted"><?= e($output['item_name']) ?></small></td>
                                        <td id="press-run-output-row-<?= e($oid) ?>-lot"><?php if ($output['lot_id']): ?><a <?= nav_attrs('/lots/' . (int) $output['lot_id']) ?>><?= e($output['lot_number']) ?></a><?php else: ?><span class="text-muted">On posting</span><?php endif; ?></td>
                                        <td id="press-run-output-row-<?= e($oid) ?>-qty" class="fw-semibold"><?= fmt_qty_html($output['qty_base'], $output['base_unit_code'], 1) ?></td>
                                        <td id="press-run-output-row-<?= e($oid) ?>-brix"><?= $output['brix'] !== null ? e(number_format((float) $output['brix'], 1)) . ' °Bx' : '' ?></td>
                                        <td id="press-run-output-row-<?= e($oid) ?>-place"><?= e($output['vessel_name'] ?? $output['location_name'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($outputs === []): ?><tr id="press-run-view-outputs-empty"><td colspan="6" class="text-center text-muted py-4">No outputs yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
