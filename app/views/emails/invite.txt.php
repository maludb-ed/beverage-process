<?php /** @var string $name  @var string $url  @var int $days  @var string $invitedBy */ ?>
Hello <?= $name ?>,

<?= $invitedBy ?> invited you to <?= config('app.name') ?>. Set your password with this link; it works for <?= $days ?> days:

<?= $url ?>
