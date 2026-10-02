<?php /** @var array $version  @var array $stages  @var array $lines  @var array $errors  @var array $stageErrors  @var array $lineErrors  @var array $catalog  @var array $stageOptions  @var array $stageNames */
$id = (int) $version['id'];
$productId = (int) $version['product_id'];
$p = 'recipe-form';
$title = 'Edit ' . $version['product_name'] . ' v' . $version['version_no'];
$cancelUrl = '/recipes/' . $id;
$stageCodes = array_values(array_unique(array_filter(array_column($stages, 'stage_code'))));
$nextSeq = 'Math.max(0, ...Array.from(document.querySelectorAll("%s")).map(e => +e.value || 0)) + 1';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'recipe-edit', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', $version['product_name'] => '/products/' . $productId, 'v' . $version['version_no'] => $cancelUrl, 'Edit' => null], 'actionsHtml' => form_actions('recipe-form', $cancelUrl, 'Save Draft')]) ?>
<div class="main-content" id="recipe-edit-content">
    <form id="recipe-form" method="post" action="/recipes/save" hx-post="/recipes/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>" />
        <div class="row"><div class="col-lg-12">
            <div class="card" id="recipe-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2"><?= e($version['product_name']) ?>, version <?= e($version['version_no']) ?> <?= status_badge($version['status'], 'recipe-form-status') ?></span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Only drafts can be edited. Activating makes the version permanent.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'recipe-form-errors']) ?>
                    <?= form_input($p, 'batch_volume', 'Target batch volume (' . display_unit('L') . ')', $version['volume'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => display_unit('L')]) ?>
                    <?= form_textarea($p, 'change_note', 'Change note', $version['change_note'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="recipe-form-stages-card">
                <div class="card-header">
                    <h5 class="card-title">Stages</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="recipe-form-add-stage-btn"
                            hx-get="/recipes/stage-row" hx-target="#recipe-form-stages" hx-swap="beforeend"
                            hx-vals='js:{n: "n" + Date.now(), product_id: <?= e($productId) ?>, seq: <?= sprintf($nextSeq, '#recipe-form-stages .recipe-stage-seq') ?>}'><i class="feather-plus me-1"></i>Add stage</button>
                </div>
                <div class="card-body" id="recipe-form-stages">
                    <?php foreach ($stages as $n => $stage): ?>
                        <?= view('recipes/partials/stage-row.php', ['n' => $n, 'stage' => $stage, 'stageOptions' => $stageOptions, 'rowErrors' => $stageErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" id="recipe-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="recipe-form-add-line-btn"
                            hx-get="/recipes/line-row" hx-target="#recipe-form-lines" hx-swap="beforeend" hx-include=".recipe-stage-code"
                            hx-vals='js:{n: "n" + Date.now(), seq: <?= sprintf($nextSeq, '#recipe-form-lines .recipe-line-seq') ?>}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="recipe-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('recipes/partials/line-row.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'stageCodes' => $stageCodes, 'stageNames' => $stageNames, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
