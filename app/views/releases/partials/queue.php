<?php /** @var array $batches  @var array $lots  @var array $query  @var array $user */ ?>
<div class="col-lg-12" id="release-queue-results">
    <div class="row">
        <?= view('releases/partials/queue-batches.php', ['result' => $batches, 'query' => $query, 'user' => $user]) ?>
        <?= view('releases/partials/queue-lots.php', ['result' => $lots, 'query' => $query, 'user' => $user]) ?>
    </div>
</div>
