<?php /** @var string $token  @var string $action  @var string $heading  @var array $errors */ ?>
<h2 class="fs-20 fw-bolder mb-4"><?= e($heading) ?></h2>
<h4 class="fs-13 fw-bold mb-2">Choose a password of at least <?= e(config('security.password_min_length')) ?> characters</h4>
<?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'auth-password-errors']) ?>
<form method="post" action="<?= e($action) ?>" class="w-100 mt-4 pt-2" id="auth-password-form">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-4">
        <input type="password" class="form-control" id="auth-password-field-password" name="password" placeholder="New password" required autocomplete="new-password" minlength="<?= e(config('security.password_min_length')) ?>" autofocus>
    </div>
    <div class="mb-3">
        <input type="password" class="form-control" id="auth-password-field-confirm" name="password_confirm" placeholder="Repeat the password" required autocomplete="new-password">
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-lg btn-primary w-100" id="auth-password-submit-btn">Save password</button>
    </div>
</form>
