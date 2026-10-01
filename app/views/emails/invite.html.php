<?php /** @var string $name  @var string $url  @var int $days  @var string $invitedBy */ ?>
<p>Hello <?= e($name) ?>,</p>
<p><?= e($invitedBy) ?> invited you to <?= e(config('app.name')) ?>. Set your password with this link; it works for <?= e($days) ?> days:</p>
<p><a href="<?= e($url) ?>"><?= e($url) ?></a></p>
