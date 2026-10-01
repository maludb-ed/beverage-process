<?php /** @var array $user  @var array $errors  @var ?string $saved */
$actions = '<button type="submit" form="settings-profile-form" id="settings-profile-save-btn" class="btn btn-primary"><i class="feather-check me-2"></i><span>Save</span></button>';
?>
<?= view('shared/page-header.php', ['title' => 'My profile', 'screen' => 'settings-profile', 'crumbs' => ['Settings' => null, 'My profile' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="settings-profile-content">
    <form id="settings-profile-form" method="post" action="/settings/profile" hx-post="/settings/profile" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card border-top-0" id="settings-profile-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="settings-profile-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="settings-profile-tab-profile" class="nav-link active" data-bs-toggle="tab" data-bs-target="#settings-profile-pane-profile" role="tab">Profile</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="settings-profile-tab-password" class="nav-link" data-bs-toggle="tab" data-bs-target="#settings-profile-pane-password" role="tab">Password</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="settings-profile-pane-profile" role="tabpanel">
                        <div class="card-body">
                            <?php if ($saved): ?><div class="alert alert-success" id="settings-profile-saved"><?= e($saved) ?></div><?php endif; ?>
                            <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'settings-profile-errors']) ?>
                            <div class="row mb-4 align-items-center" id="settings-profile-field-display-name-row">
                                <div class="col-lg-4"><label id="settings-profile-field-display-name-label" for="settings-profile-field-display-name" class="fw-semibold">Name:</label></div>
                                <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-user"></i></div>
                                    <input type="text" class="form-control" id="settings-profile-field-display-name" name="display_name" value="<?= e($user['display_name']) ?>" required maxlength="120"></div></div>
                            </div>
                            <div class="row mb-4 align-items-center" id="settings-profile-field-email-row">
                                <div class="col-lg-4"><label id="settings-profile-field-email-label" for="settings-profile-field-email" class="fw-semibold">Email:</label></div>
                                <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-mail"></i></div>
                                    <input type="email" class="form-control" id="settings-profile-field-email" value="<?= e($user['email']) ?>" disabled></div></div>
                            </div>
                            <div class="row mb-0 align-items-center" id="settings-profile-field-role-row">
                                <div class="col-lg-4"><label id="settings-profile-field-role-label" class="fw-semibold">Role:</label></div>
                                <div class="col-lg-8"><span class="badge bg-soft-success text-success" id="settings-profile-field-role"><?= e(ucfirst($user['role'])) ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="settings-profile-pane-password" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Change password</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Leave blank to keep your current password.</span></h5></div>
                            <div class="row mb-4 align-items-center" id="settings-profile-field-current-password-row">
                                <div class="col-lg-4"><label id="settings-profile-field-current-password-label" for="settings-profile-field-current-password" class="fw-semibold">Current password:</label></div>
                                <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-lock"></i></div>
                                    <input type="password" class="form-control" id="settings-profile-field-current-password" name="current_password" autocomplete="current-password"></div></div>
                            </div>
                            <div class="row mb-4 align-items-center" id="settings-profile-field-new-password-row">
                                <div class="col-lg-4"><label id="settings-profile-field-new-password-label" for="settings-profile-field-new-password" class="fw-semibold">New password:</label></div>
                                <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-key"></i></div>
                                    <input type="password" class="form-control" id="settings-profile-field-new-password" name="new_password" autocomplete="new-password"></div></div>
                            </div>
                            <div class="row mb-0 align-items-center" id="settings-profile-field-confirm-password-row">
                                <div class="col-lg-4"><label id="settings-profile-field-confirm-password-label" for="settings-profile-field-confirm-password" class="fw-semibold">Repeat new password:</label></div>
                                <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-key"></i></div>
                                    <input type="password" class="form-control" id="settings-profile-field-confirm-password" name="confirm_password" autocomplete="new-password"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div></div>
    </form>
</div>
