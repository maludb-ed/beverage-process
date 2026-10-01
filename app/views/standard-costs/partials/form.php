<?php /** @var array $input  @var array $errors  @var array $catalog  @var array $history */
$p = 'standard-cost-form';
$options = [];
foreach ($catalog as $itemId => $item) {
    $options[$itemId] = $item['code'] . ' — ' . $item['name'] . ' (per ' . display_unit($item['base_unit_code'], standard_cost_unit_kind($item['item_class'])) . ')';
}
?>
<?= view('shared/page-header.php', ['title' => 'Set Standard Cost', 'screen' => 'standard-cost-form', 'crumbs' => ['Products' => null, 'Standard costs' => '/standard-costs/', 'Set' => null], 'actionsHtml' => form_actions('standard-cost-form', '/standard-costs/', 'Save Cost')]) ?>
<div class="main-content" id="standard-cost-form-content">
    <form id="standard-cost-form" method="post" action="/standard-costs/save" hx-post="/standard-costs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="standard-cost-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Standard cost</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Entered per display unit (pound, gallon or each); stored per base unit. History is kept, not edited.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'standard-cost-form-errors']) ?>
                    <?= form_select($p, 'item', 'Item', $options, $input['item_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose an item', 'name' => 'item_id']) ?>
                    <?= form_input($p, 'cost', 'Cost per display unit', $input['cost'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-dollar-sign']) ?>
                    <?= form_input($p, 'effective_from', 'Effective from', $input['effective_from'] ?? today(), $errors, ['type' => 'date', 'required' => true, 'icon' => 'feather-calendar', 'last' => true]) ?>
                </div>
            </div>
            <?php if ($history !== []): ?>
            <div class="card stretch stretch-full" id="standard-cost-form-history-card">
                <div class="card-header"><h5 class="card-title">History</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="standard-cost-form-history-table">
                        <thead class="thead-light"><tr><th>Effective from</th><th>Cost per base unit</th><th>Entered by</th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr id="standard-cost-history-row-<?= e($row['id']) ?>"><td><?= e(format_date($row['effective_from'])) ?></td><td>$<?= e(number_format((float) $row['cost_per_base'], 6)) ?></td><td><?= e($row['created_by_name']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div></div>
    </form>
</div>
