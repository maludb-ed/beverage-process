<?php /** @var string $name  @var string $url  @var int $minutes */ ?>
<p>Hello <?= e($name) ?>,</p>
<p>Someone asked to reset the password for your <?= e(config('app.name')) ?> account. If that was you, open this link within <?= e($minutes) ?> minutes:</p>
<p><a href="<?= e($url) ?>"><?= e($url) ?></a></p>
<p>If you did not ask for this, ignore this message; your password stays the same.</p>
