<?php /** @var array $product  @var array $specs  @var bool $canEdit */
$productId = (int) $product['id'];
$actions = nav_button('specs-list-back-btn', '/products/' . $productId . '?tab=specs', 'Product', 'feather-arrow-left', 'btn btn-light-brand')
    . ($canEdit ? nav_button('specs-list-add-btn', '/products/' . $productId . '/specs/new', 'Add Spec') : '');
$rowsHtml = '';
$lastStage = null;
foreach ($specs as $spec) {
    if ($spec['stage_code'] !== $lastStage) {
        $rowsHtml .= view('specs/partials/stage-row.php', ['spec' => $spec]);
        $lastStage = $spec['stage_code'];
    }
    $rowsHtml .= view('specs/partials/row.php', ['spec' => $spec, 'canEdit' => $canEdit]);
}
?>
<?= view('shared/page-header.php', ['title' => 'Specs: ' . $product['name'], 'screen' => 'specs-list', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', $product['name'] => '/products/' . $productId, 'Specs' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="specs-list-content">
    <div class="row">
        <?= view('specs/partials/table.php', ['specs' => $specs, 'rowsHtml' => $rowsHtml, 'productId' => $productId]) ?>
    </div>
</div>
