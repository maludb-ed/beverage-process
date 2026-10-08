<?php /** @var string $subjectKind  @var array $subjectOptions  @var int|null $selected  @var array $errors */
$p = 'reservation-form';
$id = field_id($p, 'subject_id');
?>
<select class="form-select<?= invalid_class($errors, 'subject_id') ?>" id="<?= e($id) ?>" name="subject_id">
    <option value="">Choose the <?= e(strtolower(RESERVATION_SUBJECT_KINDS[$subjectKind] ?? 'run')) ?></option>
    <?php foreach ($subjectOptions as $sid => $label): ?>
        <option value="<?= e($sid) ?>"<?= (int) $sid === (int) ($selected ?? 0) ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
</select>
<?php if ($subjectOptions === []): ?><div class="fs-11 text-muted mt-1" id="<?= e($id) ?>-none">No open <?= e(strtolower(RESERVATION_SUBJECT_KINDS[$subjectKind] ?? 'run')) ?>s to book for.</div><?php endif; ?>
<?php if (isset($errors['subject_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['subject_id']) ?></div><?php endif; ?>
