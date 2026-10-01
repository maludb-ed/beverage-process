<?php /** @var string $p (line id prefix)  @var string $base (field name base)  @var array $tag  @var array $lineErrors */
$unit = display_unit('kg', 'fruit');
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
$gross = $tag['gross'] ?? (isset($tag['gross_kg']) ? round((float) to_display($tag['gross_kg'], 'kg', 'fruit'), 1) : '');
$tare = $tag['tare'] ?? (isset($tag['tare_kg']) ? round((float) to_display($tag['tare_kg'], 'kg', 'fruit'), 1) : '');
$net = is_numeric($gross) ? max(0, (float) $gross - (float) $tare) : null;
?>
<div class="bg-gray-100 rounded p-3 mt-3" id="<?= e($p) ?>-weigh-tag">
    <div class="fw-semibold fs-12 mb-2"><i class="feather-clipboard me-1"></i>Weigh tag (weights in <?= e($unit) ?>)</div>
    <div class="row g-2">
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-tag-number">Tag #</label><input type="text" class="form-control" id="<?= e($p) ?>-tag-number" name="<?= e($base) ?>[weigh_tag][tag_number]" value="<?= e($tag['tag_number'] ?? '') ?>" maxlength="40" /></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-gross">Gross</label><input type="number" step="any" min="0" class="form-control<?= $inv('weigh_tag_gross') ?>" id="<?= e($p) ?>-gross" name="<?= e($base) ?>[weigh_tag][gross]" value="<?= e($gross) ?>" /><?= $err('weigh_tag_gross') ?></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-tare">Tare</label><input type="number" step="any" min="0" class="form-control<?= $inv('weigh_tag_tare') ?>" id="<?= e($p) ?>-tare" name="<?= e($base) ?>[weigh_tag][tare]" value="<?= e($tare) ?>" /><?= $err('weigh_tag_tare') ?></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-net">Net</label><input type="text" class="form-control" id="<?= e($p) ?>-net" value="<?= e($net !== null ? number_format($net, 1) : '') ?>" readonly tabindex="-1" /></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-bin-count">Bins</label><input type="number" step="1" min="0" class="form-control" id="<?= e($p) ?>-bin-count" name="<?= e($base) ?>[weigh_tag][bin_count]" value="<?= e($tag['bin_count'] ?? '') ?>" /></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-brix">Brix</label><input type="number" step="0.1" min="0" max="40" class="form-control<?= $inv('weigh_tag_brix') ?>" id="<?= e($p) ?>-brix" name="<?= e($base) ?>[weigh_tag][brix]" value="<?= e($tag['brix_at_receipt'] ?? '') ?>" /><?= $err('weigh_tag_brix') ?></div>
        <div class="col-6 col-md-3"><label class="fs-11" for="<?= e($p) ?>-variety">Variety</label><input type="text" class="form-control" id="<?= e($p) ?>-variety" name="<?= e($base) ?>[weigh_tag][variety]" value="<?= e($tag['variety'] ?? '') ?>" maxlength="80" /></div>
        <div class="col-6 col-md-3"><label class="fs-11" for="<?= e($p) ?>-orchard">Orchard</label><input type="text" class="form-control" id="<?= e($p) ?>-orchard" name="<?= e($base) ?>[weigh_tag][orchard]" value="<?= e($tag['orchard'] ?? '') ?>" maxlength="80" /></div>
        <div class="col-6 col-md-2"><label class="fs-11" for="<?= e($p) ?>-block">Block</label><input type="text" class="form-control" id="<?= e($p) ?>-block" name="<?= e($base) ?>[weigh_tag][block]" value="<?= e($tag['block'] ?? '') ?>" maxlength="40" /></div>
        <div class="col-12 col-md-4"><label class="fs-11" for="<?= e($p) ?>-condition-note">Condition</label><input type="text" class="form-control" id="<?= e($p) ?>-condition-note" name="<?= e($base) ?>[weigh_tag][condition_note]" value="<?= e($tag['condition_note'] ?? '') ?>" maxlength="200" /></div>
    </div>
</div>
