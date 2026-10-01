<?php /** @var array $summary */
$tiles = [
    'batches' => ['Batches affected', 'feather-droplet', number_format($summary['batches'])],
    'finished-lots' => ['Finished lots affected', 'feather-box', number_format($summary['finished_lots'])],
    'units-on-hand' => ['Units still on hand', 'feather-package', number_format($summary['units_on_hand'])],
    'customers' => ['Customers affected', 'feather-users', number_format($summary['customers'])],
];
?>
<div class="row" id="trace-summary">
    <?php foreach ($tiles as $key => [$label, $icon, $value]): ?>
        <div class="col-6 col-xxl-3 col-md-6">
            <div class="card stretch stretch-full" id="trace-summary-<?= e($key) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-text avatar-lg bg-gray-200"><i class="<?= e($icon) ?>"></i></div>
                        <div>
                            <div class="fs-4 fw-bold text-dark" id="trace-summary-<?= e($key) ?>-value"><?= e($value) ?></div>
                            <div class="fs-12 text-muted"><?= e($label) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
