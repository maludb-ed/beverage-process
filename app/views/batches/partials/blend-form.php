<?php /** @var array $input  @var array $rows  @var array $errors  @var array $rowErrors  @var array $batches  @var array $vessels  @var array $products  @var array $recipes */
$p = 'batch-blend-form';
$rowsHtml = '';
foreach ($rows as $n => $row) {
    $rowsHtml .= view('batches/partials/split-row.php', ['n' => $n, 'row' => $row, 'vessels' => [], 'batches' => $batches, 'rowErrors' => $rowErrors[$n] ?? [], 'prefix' => 'batch-blend-form-input-row', 'base' => 'inputs', 'kind' => 'blend']);
}
?>
<?= view('shared/page-header.php', ['title' => 'Blend', 'screen' => 'batch-blend-form', 'crumbs' => ['Production' => null, 'Batches' => '/batches/', 'Blend' => null], 'actionsHtml' => form_actions('batch-blend-form', '/batches/', 'Save Blend')]) ?>
<div class="main-content" id="batch-blend-form-content">
    <form id="batch-blend-form" method="post" action="/batches/blend" hx-post="/batches/blend" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="batch-blend-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Blend batches</span><span class="fs-12 fw-normal text-muted">The inputs become one new batch; its fruit share is the volume-weighted average.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'batch-blend-form-errors']) ?>
                    <?= form_select($p, 'vessel', 'Into vessel', $vessels, $input['vessel_id'] ?? '', $errors, ['name' => 'vessel_id', 'required' => true, 'blank' => 'Choose a vessel', 'help' => 'An occupied vessel can be used only when it holds one of the inputs, blended in full.']) ?>
                    <?= form_select($p, 'product', 'Product', $products, $input['product_id'] ?? '', $errors, ['name' => 'product_id', 'blank' => 'The product of the largest input']) ?>
                    <?= form_select($p, 'recipe_version', 'Recipe version', $recipes, $input['recipe_version_id'] ?? '', $errors, ['name' => 'recipe_version_id', 'blank' => 'No recipe']) ?>
                    <?= form_input($p, 'blended_at', 'Blended at', $input['blended_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors) ?>
                    <div class="d-flex align-items-center justify-content-between mb-3"><h6 class="fw-bold mb-0">Inputs</h6>
                        <button type="button" class="btn btn-sm btn-light-brand" id="batch-blend-form-add-input-btn" hx-get="/batches/blend-row" hx-target="#batch-blend-form-inputs" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add input</button>
                    </div>
                    <div id="batch-blend-form-inputs"><?= $rowsHtml ?></div>
                </div>
            </div>
        </div></div>
    </form>
</div>
