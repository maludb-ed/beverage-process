<?php /** @var string $name  @var string $url  @var int $minutes */ ?>
Hello <?= $name ?>,

Someone asked to reset the password for your <?= config('app.name') ?> account. If that was you, open this link within <?= $minutes ?> minutes:

<?= $url ?>

If you did not ask for this, ignore this message; your password stays the same.
