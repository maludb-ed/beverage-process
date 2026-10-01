<?php /** @var string $email  @var ?string $error  @var string $next  @var bool $googleEnabled */ ?>
<h2 class="fs-20 fw-bolder mb-4">Sign in</h2>
<h4 class="fs-13 fw-bold mb-2">Sign in to <?= e(config('app.name')) ?></h4>
<p class="fs-12 fw-medium text-muted">Your production, inventory and compliance memory.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert" id="auth-login-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/login" class="w-100 mt-4 pt-2" id="auth-login-form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="mb-4">
        <input type="email" class="form-control" id="auth-login-field-email" name="email" placeholder="Email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
    </div>
    <div class="mb-3">
        <input type="password" class="form-control" id="auth-login-field-password" name="password" placeholder="Password" required autocomplete="current-password">
    </div>
    <div class="d-flex align-items-center justify-content-end">
        <a href="/password/reset" class="fs-11 text-primary" id="auth-login-forgot-link">Forgot password?</a>
    </div>
    <div class="mt-5">
        <button type="submit" class="btn btn-lg btn-primary w-100" id="auth-login-submit-btn">Sign in</button>
    </div>
</form>
<div class="w-100 mt-5 text-center mx-auto">
    <div class="mb-4 border-bottom position-relative"><span class="small py-1 px-3 text-uppercase text-muted bg-white position-absolute translate-middle">or</span></div>
    <a href="/auth/google/start" class="btn btn-light-brand w-100" id="auth-login-google-btn"<?= $googleEnabled ? '' : ' aria-disabled="true"' ?>>
        <i class="feather-chrome me-2"></i><span>Sign in with Google</span>
    </a>
    <?php if (!$googleEnabled): ?><p class="fs-11 text-muted mt-2 mb-0" id="auth-login-google-note">Google sign-in is not configured for this installation yet.</p><?php endif; ?>
</div>
