<?php /** @var array $count  @var array $errors  @var array $locations */
$p = 'count-form';
?>
<?= view('shared/page-header.php', ['title' => 'Start Count', 'screen' => 'count-form', 'crumbs' => ['Inventory' => null, 'Counts' => '/counts/', 'Start' => null], 'actionsHtml' => form_actions('count-form', '/counts/', 'Start Count')]) ?>
<div class="main-content" id="count-form-content">
    <form id="count-form" method="post" action="/counts/save" hx-post="/counts/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="count-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Count a location</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">The count sheet is filled with what the system expects at the location.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'count-form-errors']) ?>
                    <?= form_select($p, 'location', 'Location', inventory_location_options($locations), $count['location_id'] ?? '', $errors, ['name' => 'location_id', 'required' => true, 'blank' => 'Choose a location']) ?>
                    <?= form_select($p, 'kind', 'Kind', COUNT_KINDS, $count['kind'] ?? 'cycle', $errors, ['required' => true]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $count['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
