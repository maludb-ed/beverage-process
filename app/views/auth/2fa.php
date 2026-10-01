<?php /** @var ?string $error */ ?>
<h2 class="fs-20 fw-bolder mb-4">Two-factor code</h2>
<h4 class="fs-13 fw-bold mb-2">Enter the code from your authenticator app</h4>
<p class="fs-12 fw-medium text-muted">Or use one of your recovery codes.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert" id="auth-2fa-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/login/2fa" class="w-100 mt-4 pt-2" id="auth-2fa-form">
    <?= csrf_field() ?>
    <div class="mb-4">
        <input type="text" class="form-control" id="auth-2fa-field-code" name="code" placeholder="123456 or XXXXX-XXXXX" required autocomplete="one-time-code" inputmode="text" autofocus>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-lg btn-primary w-100" id="auth-2fa-submit-btn">Verify</button>
    </div>
</form>
<div class="mt-4 text-muted fs-12">
    <a href="/login" class="fw-bold" id="auth-2fa-cancel-link">Back to sign in</a>
</div>
