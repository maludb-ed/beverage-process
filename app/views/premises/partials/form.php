<?php /** @var array $premises  @var array $errors */
$id = $premises['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Premises' : 'Add Premises';
$p = 'premises-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'premises-form', 'crumbs' => ['Setup' => null, 'Premises' => '/premises/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('premises-form', '/premises/', 'Save Premises')]) ?>
<div class="main-content" id="premises-form-content">
    <form id="premises-form" method="post" action="/premises/save" hx-post="/premises/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="premises-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">TTB premises</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">One row per permit. Every location, vessel and report belongs to a premises.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'premises-form-errors']) ?>
                    <?= form_input($p, 'name', 'Name', $premises['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-home', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Permit kind', PREMISES_KINDS, $premises['kind'] ?? 'bonded_winery', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'registry_number', 'Registry number', $premises['registry_number'] ?? '', $errors, ['maxlength' => 40, 'icon' => 'feather-hash', 'placeholder' => 'BWN-XX-12345']) ?>
                    <?= form_select($p, 'report_form', 'Report form', PREMISES_FORMS, $premises['report_form'] ?? '5120.17', $errors, ['required' => true, 'help' => 'Bonded wineries file 5120.17; breweries 5130.9 or 5130.26.']) ?>
                    <?= form_select($p, 'filing_frequency', 'Filing frequency', PREMISES_FREQUENCIES, $premises['filing_frequency'] ?? 'monthly', $errors, ['required' => true]) ?>
                    <?= form_select($p, 'tax_determination_point', 'Tax determined', PREMISES_TAX_POINTS, $premises['tax_determination_point'] ?? 'removal', $errors, ['required' => true]) ?>
                    <?= form_select($p, 'cbma_tier', 'CBMA tier', PREMISES_CBMA_TIERS, $premises['cbma_tier'] ?? 'tier1', $errors, ['required' => true]) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($premises['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
