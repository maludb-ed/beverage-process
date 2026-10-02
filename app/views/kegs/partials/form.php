<?php /** @var array $keg  @var array $errors */
$id = $keg['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($keg['serial'] ?? 'Keg') : 'Add Keg';
$cancelUrl = $isEdit ? '/kegs/' . $id : '/kegs/';
$p = 'keg-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'keg-form', 'crumbs' => ['Packaging' => null, 'Kegs' => '/kegs/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('keg-form', $cancelUrl, 'Save Keg')]) ?>
<div class="main-content" id="keg-form-content">
    <form id="keg-form" method="post" action="/kegs/save" hx-post="/kegs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="keg-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Keg</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">A serialized returnable asset. A new keg starts empty at the packaged goods location.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'keg-form-errors']) ?>
                    <?= form_input($p, 'serial', 'Serial', $keg['serial'] ?? '', $errors, ['required' => true, 'maxlength' => 60, 'icon' => 'feather-hash', 'autofocus' => !$isEdit]) ?>
                    <?= form_input($p, 'size_gal', 'Size', $keg['size_gal'] ?? '', $errors, ['type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => 'gal', 'help' => 'For example 15.5 for a half barrel.']) ?>
                    <?= form_select($p, 'ownership', 'Ownership', KEG_OWNERSHIPS, $keg['ownership'] ?? 'owned', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'deposit_amount', 'Deposit', $keg['deposit_amount'] ?? '0', $errors, ['type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true, 'icon' => 'feather-dollar-sign']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $keg['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
