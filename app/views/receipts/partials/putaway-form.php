<?php /** @var array $receipt  @var array $rows  @var array $destinations  @var array $errors */
$id = (int) $receipt['id'];
?>
<?= view('shared/page-header.php', ['title' => 'Putaway ' . $receipt['number'], 'screen' => 'putaway-form', 'crumbs' => ['Receiving' => null, 'Receipts' => '/receipts/', $receipt['number'] => '/receipts/' . $id, 'Putaway' => null], 'actionsHtml' => form_actions('putaway-form', '/receipts/' . $id, 'Move stock')]) ?>
<div class="main-content" id="putaway-form-content">
    <form id="putaway-form" method="post" action="/receipts/<?= e($id) ?>/putaway" hx-post="/receipts/<?= e($id) ?>/putaway" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="putaway-form-card">
                <div class="card-header"><h5 class="card-title">Move from <?= e($receipt['receiving_location_name']) ?></h5></div>
                <div class="card-body">
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'putaway-form-errors']) ?>
                    <?php if ($rows === []): ?><p class="text-muted mb-0" id="putaway-form-empty">Everything from this receipt has already left the receiving location.</p><?php endif; ?>
                    <?php foreach ($rows as $n => $row): ?>
                        <div class="row mb-4 align-items-center" id="putaway-form-line-<?= e($n) ?>-row">
                            <div class="col-lg-4">
                                <div class="fw-semibold" id="putaway-form-line-<?= e($n) ?>-lot"><?= e($row['lot_number']) ?></div>
                                <div class="fs-12 text-muted"><?= e($row['item_code'] . ' — ' . $row['item_name']) ?>, <?= e(fmt_qty($row['qty_on_hand'], $row['base_unit_code'], 1, $row['item_class'] === 'fruit' ? 'fruit' : 'default')) ?> at receiving</div>
                            </div>
                            <div class="col-lg-8">
                                <input type="hidden" name="lines[<?= e($n) ?>][line_id]" value="<?= e($row['line_id']) ?>" />
                                <select class="form-select" id="putaway-form-line-<?= e($n) ?>-to-location-id" name="lines[<?= e($n) ?>][to_location_id]">
                                    <option value="">Leave at <?= e($receipt['receiving_location_name']) ?></option>
                                    <?php foreach ($destinations as $locId => $locName): if ((int) $locId === (int) $receipt['receiving_location_id']) { continue; } ?>
                                        <option value="<?= e($locId) ?>"<?= (int) ($row['to_location_id'] ?? 0) === (int) $locId ? ' selected' : '' ?>><?= e($locName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p class="fs-12 text-muted mb-0">Only locations with the same tax state (<?= e(humanize($receipt['receiving_tax_state'])) ?>) are offered; moving across tax states is a removal.</p>
                </div>
            </div>
        </div></div>
    </form>
</div>
