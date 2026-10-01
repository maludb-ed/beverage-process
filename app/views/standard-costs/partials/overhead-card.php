<?php /** @var array $overheadRates  @var bool $canEdit */ ?>
<div class="col-lg-12" id="standard-costs-overhead-card">
    <div class="card stretch stretch-full" id="standard-costs-overhead">
        <div class="card-header">
            <h5 class="card-title">Overhead rate per premises</h5>
            <?php if ($canEdit): ?><?= nav_button('standard-costs-overhead-add-btn', '/standard-costs/overhead', 'Set Overhead Rate', 'feather-plus', 'btn btn-sm btn-light-brand') ?><?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="standard-costs-overhead-table">
                    <thead class="thead-light"><tr><th>Premises</th><th>Rate per <?= e(display_unit('L')) ?></th><th>Rate per L</th><th>Effective from</th></tr></thead>
                    <tbody>
                    <?php foreach ($overheadRates as $rate): $rid = (int) $rate['premises_id']; ?>
                        <tr id="standard-costs-overhead-row-<?= e($rid) ?>">
                            <td id="standard-costs-overhead-row-<?= e($rid) ?>-premises"><?= e($rate['premises_name']) ?></td>
                            <td id="standard-costs-overhead-row-<?= e($rid) ?>-rate" class="fw-semibold"><?= $rate['rate_per_l'] === null ? '<span class="text-muted fw-normal">not set</span>' : '$' . e(number_format((float) $rate['rate_per_l'] * unit_factor(display_unit('L')), 4)) . ' / ' . e(display_unit('L')) ?></td>
                            <td id="standard-costs-overhead-row-<?= e($rid) ?>-rate-per-l"><?= $rate['rate_per_l'] === null ? '' : '$' . e(number_format((float) $rate['rate_per_l'], 4)) . ' / L' ?></td>
                            <td id="standard-costs-overhead-row-<?= e($rid) ?>-effective-from"><?= e(format_date($rate['effective_from'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($overheadRates === []): ?><tr id="standard-costs-overhead-empty"><td colspan="4" class="text-center text-muted py-4">No active premises.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
