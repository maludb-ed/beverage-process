<?php /** @var array $target  @var int $currentUserId */
$id = (int) $target['id']; $editUrl = '/users/' . $id . '/edit'; ?>
<tr id="user-row-<?= e($id) ?>">
    <td id="user-row-<?= e($id) ?>-display-name">
        <a <?= nav_attrs($editUrl) ?>><?= status_dot(status_color($target['status'])) ?><span><?= e($target['display_name']) ?></span></a>
    </td>
    <td id="user-row-<?= e($id) ?>-email"><?= e($target['email']) ?></td>
    <td id="user-row-<?= e($id) ?>-role"><?= badge(USER_ROLES[$target['role']] ?? $target['role'], 'info') ?></td>
    <td id="user-row-<?= e($id) ?>-status"><?= status_badge($target['status']) ?></td>
    <td id="user-row-<?= e($id) ?>-last-login-at"><?= e(format_date($target['last_login_at'])) ?></td>
    <td id="user-row-<?= e($id) ?>-two-factor"><?= $target['totp_enabled_at'] !== null ? badge('On', 'success') : '<span class="text-muted">Off</span>' ?></td>
    <td id="user-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?= row_edit_button('user-row-' . $id . '-edit-btn', $editUrl) ?>
            <?php if ($target['status'] === 'invited'): ?>
                <a id="user-row-<?= e($id) ?>-resend-btn" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Resend invitation"
                   hx-post="/users/<?= e($id) ?>/resend" hx-target="#page-content" hx-swap="innerHTML"
                   hx-confirm="Send <?= e($target['display_name']) ?> a new invitation at <?= e($target['email']) ?>? Earlier invitation links will stop working."><i class="feather-mail"></i></a>
            <?php endif; ?>
            <?php if ($target['status'] === 'disabled'): ?>
                <a id="user-row-<?= e($id) ?>-enable-btn" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Enable"
                   hx-post="/users/<?= e($id) ?>/enable" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-user-check"></i></a>
            <?php endif; ?>
            <?php if ($target['status'] !== 'disabled' && $id !== $currentUserId): ?>
                <a id="user-row-<?= e($id) ?>-disable-btn" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Disable"
                   hx-post="/users/<?= e($id) ?>/disable" hx-target="#page-content" hx-swap="innerHTML"
                   hx-confirm="Disable <?= e($target['display_name']) ?>? They will no longer be able to sign in."><i class="feather-user-x"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
