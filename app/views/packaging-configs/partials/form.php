<?php /** @var array $config  @var array $lines  @var array $errors  @var array $lineErrors  @var array $catalog  @var array $products  @var array $finishedItems  @var array $fillUnits  @var bool $canPrice */
$id = $config['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($config['name'] ?? 'Packaging Configuration') : 'Add Packaging Configuration';
$p = 'packaging-config-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'packaging-config-form', 'crumbs' => ['Products' => null, 'Packaging configurations' => '/packaging-configs/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('packaging-config-form', '/packaging-configs/', 'Save Configuration')]) ?>
<div class="main-content" id="packaging-config-form-content">
    <form id="packaging-config-form" method="post" action="/packaging-configs/save" hx-post="/packaging-configs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="packaging-config-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Packaging configuration</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">How a product is packaged: the finished item, the fill volume and the bill of materials per unit.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'packaging-config-form-errors']) ?>
                    <?= form_select($p, 'product', 'Product', $products, $config['product_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a product', 'name' => 'product_id']) ?>
                    <?= form_select($p, 'finished_item', 'Finished item', $finishedItems, $config['finished_item_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a finished item', 'name' => 'finished_item_id']) ?>
                    <?= form_input($p, 'name', 'Name', $config['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-box']) ?>
                    <?= form_select($p, 'package_kind', 'Package kind', PACKAGE_KINDS, $config['package_kind'] ?? 'can', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'fill_volume', 'Fill volume per unit', $config['fill_volume'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-droplet']) ?>
                    <?= form_select($p, 'fill_unit', 'Fill volume unit', $fillUnits, $config['fill_unit'] ?? display_unit('L'), $errors, ['required' => true, 'help' => 'Cans are usually entered in fl oz.']) ?>
                    <?= form_input($p, 'units_per_case', 'Units per case', $config['units_per_case'] ?? '', $errors, ['type' => 'number', 'min' => 1, 'step' => '1', 'icon' => 'feather-grid', 'help' => 'Required for cans and bottles.']) ?>
                    <?= form_input($p, 'expected_loss', 'Expected loss (%)', $config['expected_loss_pct'] ?? '2', $errors, ['type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01', 'required' => true, 'icon' => 'feather-percent', 'name' => 'expected_loss_pct']) ?>
                    <?php if (!empty($canPrice)): ?>
                    <?= form_input($p, 'default_unit_price', 'List price per unit', $config['default_unit_price'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => '0.01', 'icon' => 'feather-dollar-sign', 'help' => 'Prefilled on customer order lines; each line can change it.']) ?>
                    <?php endif; ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($config['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="packaging-config-form-bom-card">
                <div class="card-header">
                    <h5 class="card-title">Bill of materials (per packaged unit)</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="packaging-config-form-add-bom-btn"
                            hx-get="/packaging-configs/bom-row" hx-target="#packaging-config-form-bom" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="packaging-config-form-bom">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('packaging-configs/partials/bom-row.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
