<?php /** @var array $token */
$id = (int) $token['id'];
$r = 'mcp-token-row-' . $id;
$revoked = $token['revoked_at'] !== null;
$service = mcp_token_is_service($token);
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-name">
        <a href="javascript:void(0);"><?= status_dot($revoked ? status_color('disabled') : status_color('active')) ?><span><?= e($token['name']) ?></span></a>
        <?php if ($service): ?><div class="fs-11 text-muted" id="<?= e($r) ?>-service-note">Used by the built-in assistant</div><?php endif; ?>
    </td>
    <td id="<?= e($r) ?>-scope"><?= badge(ucfirst($token['scope']), 'info') ?></td>
    <td id="<?= e($r) ?>-prefix"><code><?= e($token['token_prefix']) ?>…</code></td>
    <td id="<?= e($r) ?>-created-at"><?= e(format_date($token['created_at'])) ?><?php if ($token['created_by_name']): ?><div class="fs-11 text-muted"><?= e($token['created_by_name']) ?></div><?php endif; ?></td>
    <td id="<?= e($r) ?>-last-used-at"><?= $token['last_used_at'] !== null ? e(format_datetime($token['last_used_at'])) : '<span class="text-muted">Never</span>' ?></td>
    <td id="<?= e($r) ?>-status">
        <?= $revoked ? badge('Revoked', status_color('disabled')) : status_badge('active') ?>
        <?php if ($revoked): ?><div class="fs-11 text-muted"><?= e(format_date($token['revoked_at'])) ?><?= $token['revoked_by_name'] ? ' by ' . e($token['revoked_by_name']) : '' ?></div><?php endif; ?>
    </td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if (!$revoked && !$service): ?>
                <a id="<?= e($r) ?>-revoke-btn" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Revoke"
                   hx-post="/settings/mcp-tokens/<?= e($id) ?>/revoke" hx-target="#page-content" hx-swap="innerHTML"
                   hx-confirm="Revoke <?= e($token['name']) ?>? Any tool using it loses access immediately."><i class="feather-slash"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
