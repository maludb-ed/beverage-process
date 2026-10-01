<?php /** @var array $taxStates  bonded/tax_paid => value */ ?>
<div class="col-lg-12" id="report-valuation-tax-state-card">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title">Bonded and tax paid</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="report-valuation-tax-state-table">
                    <thead class="thead-light"><tr><th id="report-valuation-tax-state-col-state">Tax state</th><th id="report-valuation-tax-state-col-value">Value</th></tr></thead>
                    <tbody id="report-valuation-tax-state-tbody">
                        <?php foreach (['bonded', 'tax_paid'] as $state): ?>
                            <tr id="report-valuation-tax-state-row-<?= e(str_replace('_', '-', $state)) ?>"><td><?= badge(humanize($state), $state === 'bonded' ? 'info' : 'success') ?></td><td>$<?= e(number_format((float) $taxStates[$state], 2)) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
