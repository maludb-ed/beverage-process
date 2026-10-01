<?php /** @var array $rows  @var array $query  @var float $total  @var array $taxStates */ ?>
<div class="col-12" id="report-valuation-results">
    <div class="row">
        <?= view('reports/partials/valuation-table.php', ['rows' => $rows, 'query' => $query, 'total' => $total]) ?>
        <?= view('reports/partials/valuation-tax-state.php', ['taxStates' => $taxStates]) ?>
    </div>
</div>
