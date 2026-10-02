<?php
/** @var array $user  @var string $mode (off|enrolling|on|codes)  @var ?string $qr  @var ?string $secret  @var array $errors  @var array $recoveryCodes  @var int $codesLeft */
$title = 'Two-factor authentication';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'settings-2fa', 'crumbs' => ['Settings' => null, $title => null]]) ?>
<div class="main-content" id="settings-2fa-content">
    <div class="row"><div class="col-lg-12">
        <div class="card" id="settings-2fa-card">
            <div class="card-header"><h5 class="card-title">Authenticator app</h5></div>
            <div class="card-body">
                <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'settings-2fa-errors']) ?>
                <?php if ($mode === 'off'): ?>
                    <p class="text-muted" id="settings-2fa-status">Two-factor authentication is <strong>off</strong>. Turn it on to require a code from an authenticator app (Google Authenticator, 1Password, Authy) at every sign-in, including Google sign-in.</p>
                    <form method="post" action="/settings/2fa" hx-post="/settings/2fa" hx-target="#page-content" hx-swap="innerHTML" id="settings-2fa-start-form">
                        <?= csrf_field() ?><input type="hidden" name="step" value="start">
                        <button type="submit" class="btn btn-primary" id="settings-2fa-start-btn"><i class="feather-shield me-2"></i><span>Turn on</span></button>
                    </form>
                <?php elseif ($mode === 'enrolling'): ?>
                    <p class="text-muted">Scan this code with your authenticator app, then enter the six-digit code it shows to confirm.</p>
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center mb-4"><img src="<?= e($qr) ?>" alt="QR code for the authenticator app" class="img-fluid" id="settings-2fa-qr" style="max-width: 220px"></div>
                        <div class="col-md-8">
                            <p class="fs-12 text-muted mb-1">Manual entry key:</p>
                            <p class="fw-semibold" id="settings-2fa-secret"><?= e(chunk_split($secret, 4, ' ')) ?></p>
                            <form method="post" action="/settings/2fa" hx-post="/settings/2fa" hx-target="#page-content" hx-swap="innerHTML" id="settings-2fa-confirm-form">
                                <?= csrf_field() ?><input type="hidden" name="step" value="confirm">
                                <div class="row mb-4 align-items-center" id="settings-2fa-field-code-row">
                                    <div class="col-lg-4"><label id="settings-2fa-field-code-label" for="settings-2fa-field-code" class="fw-semibold">Code:</label></div>
                                    <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-hash"></i></div>
                                        <input type="text" class="form-control" id="settings-2fa-field-code" name="code" inputmode="numeric" pattern="[0-9 ]*" required autocomplete="one-time-code" autofocus></div></div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="settings-2fa-confirm-btn"><i class="feather-check me-2"></i><span>Confirm and enable</span></button>
                                <a href="/settings/2fa" class="btn btn-light-brand" id="settings-2fa-cancel-btn" hx-get="/settings/2fa?cancel=1" hx-target="#page-content" hx-swap="innerHTML">Cancel</a>
                            </form>
                        </div>
                    </div>
                <?php elseif ($mode === 'codes'): ?>
                    <div class="alert alert-success" id="settings-2fa-enabled-alert">Two-factor authentication is now on. Save these recovery codes somewhere safe; each works once and they are shown only now.</div>
                    <div class="row" id="settings-2fa-recovery-codes">
                        <?php foreach ($recoveryCodes as $i => $code): ?>
                            <div class="col-6 col-md-3 mb-2"><code id="settings-2fa-recovery-code-<?= e($i) ?>"><?= e($code) ?></code></div>
                        <?php endforeach; ?>
                    </div>
                    <a href="/settings/2fa" class="btn btn-primary mt-3" id="settings-2fa-done-btn" hx-get="/settings/2fa" hx-target="#page-content" hx-swap="innerHTML">Done</a>
                <?php else: ?>
                    <p class="text-muted" id="settings-2fa-status">Two-factor authentication is <strong>on</strong> since <?= e(format_date($user['totp_enabled_at'])) ?>. You have <strong id="settings-2fa-codes-left"><?= e($codesLeft) ?></strong> unused recovery codes.</p>
                    <form method="post" action="/settings/2fa" hx-post="/settings/2fa" hx-target="#page-content" hx-swap="innerHTML" id="settings-2fa-disable-form" hx-confirm="Turn off two-factor authentication?">
                        <?= csrf_field() ?><input type="hidden" name="step" value="disable">
                        <div class="row mb-4 align-items-center" id="settings-2fa-field-disable-code-row">
                            <div class="col-lg-4"><label id="settings-2fa-field-disable-code-label" for="settings-2fa-field-disable-code" class="fw-semibold">Current code or recovery code:</label></div>
                            <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-hash"></i></div>
                                <input type="text" class="form-control" id="settings-2fa-field-disable-code" name="code" required autocomplete="one-time-code"></div></div>
                        </div>
                        <div class="row mb-4 align-items-center" id="settings-2fa-field-disable-password-row">
                            <div class="col-lg-4"><label id="settings-2fa-field-disable-password-label" for="settings-2fa-field-disable-password" class="fw-semibold">Your password:</label></div>
                            <div class="col-lg-8"><div class="input-group"><div class="input-group-text"><i class="feather-lock"></i></div>
                                <input type="password" class="form-control" id="settings-2fa-field-disable-password" name="password" autocomplete="current-password" <?= $user['password_hash'] === null ? 'disabled placeholder="Google-only account: not required"' : 'required' ?>></div></div>
                        </div>
                        <button type="submit" class="btn btn-danger" id="settings-2fa-disable-btn"><i class="feather-shield-off me-2"></i><span>Turn off</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div></div>
</div>
