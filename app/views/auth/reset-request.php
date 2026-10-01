<?php /** @var bool $sent */ ?>
<h2 class="fs-20 fw-bolder mb-4">Reset password</h2>
<?php if ($sent): ?>
    <p class="fs-12 fw-medium text-muted" id="auth-reset-sent">If that address belongs to an account, a reset link is on its way. It expires in <?= e(config('security.reset_token_minutes')) ?> minutes.</p>
<?php else: ?>
    <h4 class="fs-13 fw-bold mb-2">We will email you a link</h4>
    <form method="post" action="/password/reset" class="w-100 mt-4 pt-2" id="auth-reset-form">
        <?= csrf_field() ?>
        <div class="mb-4">
            <input type="email" class="form-control" id="auth-reset-field-email" name="email" placeholder="Email" required autocomplete="username" autofocus>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-lg btn-primary w-100" id="auth-reset-submit-btn">Send reset link</button>
        </div>
    </form>
<?php endif; ?>
<div class="mt-4 text-muted fs-12"><a href="/login" class="fw-bold" id="auth-reset-back-link">Back to sign in</a></div>
