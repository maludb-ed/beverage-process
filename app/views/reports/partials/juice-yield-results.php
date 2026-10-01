<?php /** @var array $summary  @var array $result  @var array $query */ ?>
<div class="col-12" id="report-juice-yield-results">
    <div class="row">
        <?= view('reports/partials/juice-yield-summary.php', ['summary' => $summary]) ?>
        <?= view('reports/partials/juice-yield-table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
