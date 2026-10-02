<?php /** @var array $target  @var array $errors  @var int $currentUserId */
$id = $target['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit User' : 'Invite User';
$p = 'user-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'user-form', 'crumbs' => ['Setup' => null, 'Users' => '/users/', $isEdit ? 'Edit' : 'Invite' => null], 'actionsHtml' => form_actions('user-form', '/users/', $isEdit ? 'Save User' : 'Send Invitation')]) ?>
<div class="main-content" id="user-form-content">
    <form id="user-form" method="post" action="/users/save" hx-post="/users/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="user-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2"><?= $isEdit ? 'User' : 'Invite a user' ?></span><span class="fs-12 fw-normal text-muted text-truncate-1-line"><?= $isEdit ? 'Change the display name here. Roles are changed below.' : 'They receive an email with a link to set their password.' ?></span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'user-form-errors']) ?>
                    <?= form_input($p, 'email', 'Email', $target['email'] ?? '', $errors, ['type' => 'email', 'required' => true, 'readonly' => $isEdit, 'maxlength' => 200, 'icon' => 'feather-mail', 'autofocus' => !$isEdit]) ?>
                    <?= form_input($p, 'display_name', 'Name', $target['display_name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-user', 'autofocus' => $isEdit, 'last' => $isEdit]) ?>
                    <?php if (!$isEdit): ?>
                        <?= form_select($p, 'role', 'Role', USER_ROLES, $target['role'] ?? 'viewer', $errors, ['required' => true, 'last' => true]) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div></div>
    </form>
    <?php if ($isEdit): ?>
    <div class="row mt-4"><div class="col-lg-12">
        <div class="card" id="user-role-card">
            <div class="card-body">
                <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Role</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Status: <?= e(humanize($target['status'] ?? '')) ?>. Changing a role takes effect on the user's next request.</span></h5></div>
                <form id="user-role-form" method="post" action="/users/<?= e($id) ?>/role" hx-post="/users/<?= e($id) ?>/role" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Change this user's role?">
                    <?= csrf_field() ?>
                    <?= form_select('user-role-form', 'role', 'Role', USER_ROLES, $target['role'] ?? 'viewer', $errors, ['required' => true, 'last' => true]) ?>
                    <div class="mt-4"><button type="submit" id="user-role-form-save-btn" class="btn btn-primary"><i class="feather-check me-2"></i><span>Change Role</span></button></div>
                </form>
            </div>
        </div>
    </div></div>
    <?php endif; ?>
</div>
