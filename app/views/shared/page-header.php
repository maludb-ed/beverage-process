<?php
/**
 * Canonical page header. $title, $screen, $crumbs (label => url|null), $actionsHtml (optional).
 * A header whose actions submit a form (a Save button with form="…") is a form header: it
 * stays pinned under the top bar while the page scrolls, shows its buttons inline on phones,
 * and carries the "Unsaved changes" flag that layout.php raises when the form is edited.
 */
$crumbs = $crumbs ?? [];
$formId = preg_match('/type="submit" form="([a-z0-9-]+)"/', $actionsHtml ?? '', $m) ? $m[1] : null;
?>
<div class="page-header<?= $formId !== null ? ' page-header-form' : '' ?>" id="<?= e($screen) ?>-header"<?= $formId !== null ? ' data-form="' . e($formId) . '"' : '' ?>>
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($title) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <?php foreach ($crumbs as $label => $url): ?>
                <?php if ($url): ?>
                    <li class="breadcrumb-item"><a href="<?= e($url) ?>" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>"><?= e($label) ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><?= e($label) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none">
                <a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a>
            </div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper" id="<?= e($screen) ?>-actions">
                <?php if ($formId !== null): ?><span class="badge bg-soft-warning text-warning page-header-unsaved" id="<?= e($screen) ?>-unsaved"><i class="feather-alert-circle me-1"></i>Unsaved changes</span><?php endif; ?>
                <?= $actionsHtml ?? '' ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center">
            <a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a>
        </div>
    </div>
</div>
