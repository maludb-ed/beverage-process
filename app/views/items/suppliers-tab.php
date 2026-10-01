<?php /** @var array $item  @var array $suppliers */ $base = $item['base_unit_code']; ?>
<div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Suppliers</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Suppliers that list this item. Add them from the supplier's Items tab.</span></h5></div>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="item-view-suppliers-table">
        <thead class="thead-light">
            <tr>
                <th id="item-view-suppliers-col-supplier">Supplier</th>
                <th id="item-view-suppliers-col-supplier-sku">Supplier SKU</th>
                <th id="item-view-suppliers-col-purchase-unit">Purchase unit</th>
                <th id="item-view-suppliers-col-last-price">Last price</th>
                <th id="item-view-suppliers-col-lead-time">Lead time</th>
            </tr>
        </thead>
        <tbody id="item-view-suppliers-tbody">
            <?php if ($suppliers === []): ?>
            <tr id="item-view-suppliers-empty"><td colspan="5" class="text-center text-muted py-4">No supplier lists this item yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($suppliers as $row): $sid = (int) $row['id']; ?>
            <tr id="item-supplier-row-<?= e($sid) ?>">
                <td id="item-supplier-row-<?= e($sid) ?>-supplier"><a <?= nav_attrs('/suppliers/' . (int) $row['supplier_id']) ?>><?= status_dot($row['active'] && $row['supplier_active'] ? 'success' : 'secondary') ?><span><?= e($row['supplier_name']) ?></span></a></td>
                <td id="item-supplier-row-<?= e($sid) ?>-supplier-sku"><?= e($row['supplier_sku']) ?></td>
                <td id="item-supplier-row-<?= e($sid) ?>-purchase-unit">1 <?= e($row['purchase_unit_code']) ?> = <?= e(items_plain_number($row['to_base_factor'])) ?> <?= e($base) ?></td>
                <td id="item-supplier-row-<?= e($sid) ?>-last-price"><?= $row['last_price'] === null ? '' : e('$' . number_format((float) $row['last_price'], 4) . ' / ' . $row['purchase_unit_code']) ?></td>
                <td id="item-supplier-row-<?= e($sid) ?>-lead-time"><?= $row['lead_time_days'] === null ? '' : e($row['lead_time_days'] . ' days') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
