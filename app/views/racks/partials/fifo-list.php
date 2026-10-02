<?php /** @var array $groups  @var array $query */ ?>
<div class="row" id="rack-fifo-results">
    <?php foreach ($groups as $g): $c = 'rack-fifo-item-' . (int) $g['item_id']; ?>
    <div class="col-12" id="<?= e($c) ?>">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title" id="<?= e($c) ?>-label"><?= e($g['label']) ?> <small class="text-muted fs-12"><?= e($g['item_code']) ?></small></h5>
            </div>
            <div class="card-body custom-card-action p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= e($c) ?>-table">
                        <thead class="thead-light"><tr><th>Pick</th><th>Lot</th><th>Batch</th><th>Date</th><th>Use by</th><th>Where</th><th class="text-end">Available</th></tr></thead>
                        <tbody>
                        <?php foreach ($g['rows'] as $row): $rid = $c . '-' . (int) $row['lot_id'] . '-' . (int) $row['location_id'];
                            $isFinished = $row['batch_id'] !== null;
                            $where = $row['rack_number'] !== null ? $row['area_name'] . ' · Rack ' . $row['rack_number'] : $row['location_name'];
                            $qty = $isFinished ? number_format((float) $row['qty_available']) . ' units' : fmt_qty($row['qty_available'], $row['base_unit_code']); ?>
                            <tr id="<?= e($rid) ?>">
                                <td id="<?= e($rid) ?>-rank"><?= (int) $row['fifo_rank'] === 1 ? badge('1st', 'success') : e('#' . (int) $row['fifo_rank']) ?></td>
                                <td id="<?= e($rid) ?>-lot"><a <?= nav_attrs(($isFinished ? '/finished-lots/' : '/lots/') . (int) $row['lot_id']) ?>><?= e($row['lot_number']) ?></a></td>
                                <td id="<?= e($rid) ?>-batch"><?= $isFinished ? '<a ' . nav_attrs('/batches/' . (int) $row['batch_id']) . '>' . e($row['batch_number']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
                                <td id="<?= e($rid) ?>-date"><?= e(format_date($row['stock_date'])) ?></td>
                                <td id="<?= e($rid) ?>-use-by"><?= e(format_date($row['use_by'])) ?></td>
                                <td id="<?= e($rid) ?>-where"><?= e($where) ?></td>
                                <td id="<?= e($rid) ?>-qty" class="text-end"><?= e($qty) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($groups === []): ?>
    <div class="col-12" id="rack-fifo-empty"><div class="card"><div class="card-body text-center py-5 text-muted"><i class="feather-inbox fs-1 d-block mb-3"></i>No released stock matches.</div></div></div>
    <?php endif; ?>
</div>
