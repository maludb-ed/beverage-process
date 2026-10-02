<?php /** @var array $order  @var array $rows  @var array $errors  @var array $rowErrors  @var array $products  @var array $premises  @var array $vesselCatalog  @var array $recipes */
$id = $order['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($order['number'] ?? 'Production Order') : 'Add Production Order';
$cancelUrl = $isEdit ? '/production-orders/' . $id : '/production-orders/';
$p = 'production-order-form';
$gal = $order['planned_volume_gal'] ?? '';
$volumeL = is_numeric($gal) ? from_display((float) $gal, 'L') : null;
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'production-order-form', 'crumbs' => ['Production' => null, 'Production orders' => '/production-orders/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('production-order-form', $cancelUrl, 'Save Order')]) ?>
<div class="main-content" id="production-order-form-content">
    <form id="production-order-form" method="post" action="/production-orders/save" hx-post="/production-orders/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="production-order-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Order</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Planned orders can change; releasing allocates materials. Volumes are in <?= e(display_unit('L')) ?>.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'production-order-form-errors']) ?>
                    <?= form_select($p, 'product', 'Product', $products, $order['product_id'] ?? '', $errors, [
                        'required' => true, 'name' => 'product_id', 'blank' => 'Choose a product',
                        'extra' => ' hx-get="/production-orders/recipe-options" hx-trigger="change" hx-target="#production-order-form-recipe-slot" hx-swap="innerHTML" hx-include="#production-order-form-field-planned-volume-gal"',
                    ]) ?>
                    <div id="production-order-form-recipe-slot"><?= view('production-orders/recipe-options.php', ['recipes' => $recipes, 'selected' => $order['recipe_version_id'] ?? null, 'volumeGal' => $gal, 'oob' => false, 'errors' => $errors]) ?></div>
                    <div id="production-order-form-volume-slot"><?= form_input($p, 'planned_volume_gal', 'Planned volume', $gal, $errors, ['type' => 'number', 'step' => '0.1', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => display_unit('L')]) ?></div>
                    <?= form_input($p, 'planned_pitch_on', 'Planned pitch date', $order['planned_pitch_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_input($p, 'planned_package_on', 'Planned package date', $order['planned_package_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?php if (count($premises) === 1): ?>
                        <input type="hidden" id="production-order-form-field-premises" name="premises_id" value="<?= e(array_key_first($premises)) ?>" />
                    <?php else: ?>
                        <?= form_select($p, 'premises', 'Premises', $premises, $order['premises_id'] ?? '', $errors, ['required' => true, 'name' => 'premises_id', 'blank' => 'Choose a premises']) ?>
                    <?php endif; ?>
                    <?= form_textarea($p, 'notes', 'Notes', $order['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="production-order-form-vessels-card">
                <div class="card-header">
                    <h5 class="card-title">Vessel plan</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="production-order-form-vessel-add-btn"
                            hx-get="/production-orders/vessel-row" hx-target="#production-order-form-vessels" hx-swap="beforeend"
                            hx-include="#production-order-form-field-planned-volume-gal" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add vessel</button>
                </div>
                <div class="card-body" id="production-order-form-vessels">
                    <?php foreach ($rows as $n => $row): ?>
                        <?= view('production-orders/partials/vessel-row.php', ['n' => $n, 'row' => $row, 'rowErrors' => $rowErrors[$n] ?? [], 'vesselCatalog' => $vesselCatalog, 'volumeL' => $volumeL]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
