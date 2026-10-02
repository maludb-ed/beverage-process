<?php /** @var array $batch  @var array $juice  @var array $errors  @var array $juiceErrors  @var array $products  @var array $recipes  @var array $orders  @var array $vessels  @var array $juiceLots  @var array $yeastLots */
$p = 'batch-form';
$yeast = $yeastLots[(int) ($batch['yeast_lot_id'] ?? 0)] ?? null;
?>
<?= view('shared/page-header.php', ['title' => 'Pitch a Batch', 'screen' => 'batch-form', 'crumbs' => ['Production' => null, 'Batches' => '/batches/', 'Pitch' => null], 'actionsHtml' => form_actions('batch-form', '/batches/', 'Pitch Batch')]) ?>
<div class="main-content" id="batch-form-content">
    <form id="batch-form" method="post" action="/batches/save" hx-post="/batches/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="batch-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Pitch</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Pitching consumes the juice lots and the yeast and starts the batch in its vessel.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'batch-form-errors']) ?>
                    <?= form_select($p, 'product', 'Product', $products, $batch['product_id'] ?? '', $errors, [
                        'name' => 'product_id', 'required' => true, 'blank' => 'Choose a product',
                        'extra' => ' hx-get="/batches/recipe-options" hx-trigger="change" hx-target="#batch-form-product-slot" hx-swap="innerHTML"',
                    ]) ?>
                    <div id="batch-form-product-slot">
                        <?= view('batches/partials/recipe-options.php', ['recipes' => $recipes, 'recipeId' => $batch['recipe_version_id'] ?? '', 'orders' => $orders, 'orderId' => $batch['production_order_id'] ?? '', 'errors' => $errors]) ?>
                    </div>
                    <?= form_select($p, 'vessel', 'Vessel', $vessels, $batch['vessel_id'] ?? '', $errors, ['name' => 'vessel_id', 'required' => true, 'blank' => 'Choose the vessel the batch lives in', 'help' => 'An occupied vessel can be used only when it holds one of the juice lots below, pitched in full.']) ?>
                    <?= form_select($p, 'yeast_lot', 'Yeast lot', array_map(static fn($l) => $l['label'], $yeastLots), $batch['yeast_lot_id'] ?? '', $errors, ['name' => 'yeast_lot_id', 'required' => true, 'blank' => $yeastLots === [] ? 'No released yeast lots with stock' : 'Choose a yeast lot']) ?>
                    <?= form_input($p, 'yeast_qty', 'Yeast quantity', $batch['yeast_qty'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-hash', 'suffix' => $yeast['base_unit_code'] ?? 'base unit', 'help' => 'In the yeast item\'s base unit.']) ?>
                    <?= form_input($p, 'started_at', 'Pitched at', $batch['started_at'] ?? batches_datetime_local(), $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $batch['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="batch-form-juice-card">
                <div class="card-header">
                    <h5 class="card-title">Juice lots</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="batch-form-add-juice-btn"
                            hx-get="/batches/juice-lot-options" hx-target="#batch-form-juice" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add juice lot</button>
                </div>
                <div class="card-body" id="batch-form-juice">
                    <?php foreach ($juice as $n => $row): ?>
                        <?= view('batches/partials/juice-row.php', ['n' => $n, 'row' => $row, 'juiceLots' => $juiceLots, 'rowErrors' => $juiceErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
