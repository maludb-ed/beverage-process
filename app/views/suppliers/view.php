<?php /** @var array $supplier  @var array $items  @var array $itemInput  @var array $itemErrors  @var bool $canEdit  @var array $itemOptions  @var array $unitOptions */
$id = (int) $supplier['id'];
$actions = nav_button('supplier-view-back-btn', '/suppliers/', 'Suppliers', 'feather-arrow-left', 'btn btn-light-brand')
    . ($canEdit ? nav_button('supplier-view-edit-btn', '/suppliers/' . $id . '/edit', 'Edit Supplier', 'feather-edit') : '');
$tabs = ['overview' => 'Overview', 'items' => 'Items', 'orders' => 'Orders', 'performance' => 'Performance'];
?>
<?= view('shared/page-header.php', ['title' => $supplier['name'], 'screen' => 'supplier-view', 'crumbs' => ['Setup' => null, 'Suppliers' => '/suppliers/', $supplier['name'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="supplier-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="supplier-view-summary">
                <div class="card-body">
                    <div class="mb-4">
                        <span class="fs-14 fw-bold d-block" id="supplier-view-name"><?= status_dot($supplier['active'] ? 'success' : 'secondary') ?><?= e($supplier['name']) ?></span>
                        <span class="fs-12 fw-normal text-muted d-block" id="supplier-view-contact"><?= e($supplier['contact_name']) ?></span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4" id="supplier-view-kind"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tag"></i>Kind</span><?= badge(SUPPLIER_KINDS[$supplier['kind']] ?? $supplier['kind'], 'info') ?></li>
                        <li class="hstack justify-content-between mb-4" id="supplier-view-email"><span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span><span><?= e($supplier['email']) ?></span></li>
                        <li class="hstack justify-content-between mb-4" id="supplier-view-phone"><span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Phone</span><span><?= e($supplier['phone']) ?></span></li>
                        <li class="hstack justify-content-between mb-0" id="supplier-view-status"><span class="text-muted fw-medium hstack gap-3"><i class="feather-activity"></i>Status</span><?= status_badge($supplier['active'] ? 'active' : 'inactive') ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="supplier-view-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="supplier-view-tabs" role="tablist">
                        <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" id="supplier-view-tab-<?= e($key) ?>" class="nav-link<?= $key === 'overview' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#supplier-view-pane-<?= e($key) ?>" role="tab"><?= e($label) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active p-4" id="supplier-view-pane-overview" role="tabpanel">
                        <div class="mb-4"><h5 class="fw-bold mb-0">Supplier details:</h5></div>
                        <?= detail_row('supplier-view-detail-contact-name', 'Contact name', e($supplier['contact_name'])) ?>
                        <?= detail_row('supplier-view-detail-email', 'Email', e($supplier['email'])) ?>
                        <?= detail_row('supplier-view-detail-phone', 'Phone', e($supplier['phone'])) ?>
                        <?= detail_row('supplier-view-detail-address', 'Address', nl2br(e($supplier['address']))) ?>
                        <?= detail_row('supplier-view-detail-notes', 'Notes', nl2br(e($supplier['notes'])), true) ?>
                    </div>
                    <div class="tab-pane fade p-4" id="supplier-view-pane-items" role="tabpanel">
                        <?= view('suppliers/items-tab.php', ['supplier' => $supplier, 'items' => $items, 'itemInput' => $itemInput, 'itemErrors' => $itemErrors, 'canEdit' => $canEdit, 'itemOptions' => $itemOptions, 'unitOptions' => $unitOptions]) ?>
                    </div>
                    <div class="tab-pane fade p-4" id="supplier-view-pane-orders" role="tabpanel">
                        <?php $orders = $orders ?? []; ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="supplier-view-orders-table">
                                <thead class="thead-light"><tr><th>Number</th><th>Status</th><th>Ordered</th><th>Expected</th><th>Lines</th></tr></thead>
                                <tbody>
                                <?php foreach ($orders as $order): $oid = (int) $order['id']; ?>
                                    <tr id="supplier-order-row-<?= e($oid) ?>">
                                        <td><a <?= nav_attrs('/purchase-orders/' . $oid) ?>><?= status_dot(status_color($order['status'])) ?><?= e($order['number']) ?></a></td>
                                        <td><?= status_badge($order['status']) ?></td>
                                        <td><?= e(format_date($order['ordered_on'])) ?></td>
                                        <td class="<?= $order['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($order['expected_on'])) ?></td>
                                        <td><?= e($order['line_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($orders === []): ?><tr id="supplier-view-orders-empty"><td colspan="5" class="text-center text-muted py-4">No orders with this supplier yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade p-4" id="supplier-view-pane-performance" role="tabpanel">
                        <?php $performance = $performance ?? ['receipts' => 0, 'late_receipts' => 0, 'short_lines' => 0, 'damaged_lines' => 0]; ?>
                        <?= detail_row('supplier-view-performance-receipts', 'Posted receipts', e($performance['receipts'])) ?>
                        <?= detail_row('supplier-view-performance-late', 'Late receipts', e($performance['late_receipts'])) ?>
                        <?= detail_row('supplier-view-performance-short', 'Short lines', e($performance['short_lines'])) ?>
                        <?= detail_row('supplier-view-performance-damaged', 'Damaged lines', e($performance['damaged_lines']), true) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
