<?php /** @var array $lot  @var array $errors */
$id = (int) $lot['id'];
$p = 'lot-form';
?>
<?= view('shared/page-header.php', ['title' => 'Edit ' . $lot['lot_number'], 'screen' => 'lot-form', 'crumbs' => ['Receiving' => null, 'Lots' => '/lots/', $lot['lot_number'] => '/lots/' . $id, 'Edit' => null], 'actionsHtml' => form_actions('lot-form', '/lots/' . $id, 'Save Lot')]) ?>
<div class="main-content" id="lot-form-content">
    <form id="lot-form" method="post" action="/lots/<?= e($id) ?>/save" hx-post="/lots/<?= e($id) ?>/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="lot-form-card">
                <div class="card-body">
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'lot-form-errors']) ?>
                    <?= form_static($p, 'item', 'Item', e($lot['item_code'] . ' — ' . $lot['item_name'])) ?>
                    <?= form_static($p, 'source', 'Source', e(humanize($lot['source_kind']) . ' #' . $lot['source_id'])) ?>
                    <?= form_static($p, 'quality_status', 'Status', status_badge($lot['quality_status']) . ' <span class="fs-12 text-muted ms-2">Change it with a release decision.</span>') ?>
                    <?= form_static($p, 'unit_cost', 'Cost', e(fmt_unit_cost($lot['unit_cost_base'], $lot['base_unit_code'], $lot['item_class'] === 'fruit' ? 'fruit' : 'default'))) ?>
                    <?= form_input($p, 'supplier_lot_number', 'Supplier lot', $lot['supplier_lot_number'] ?? '', $errors, ['maxlength' => 80, 'icon' => 'feather-hash']) ?>
                    <?= form_input($p, 'expires_on', 'Expires on', $lot['expires_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $lot['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
