<?php /** @var array $imports  @var array $errors  @var string $futureStatus */ $p = 'orders-import-form'; ?>
<?= view('shared/page-header.php', ['title' => 'Import orders', 'screen' => 'orders-import', 'crumbs' => ['Sales' => null, 'Customer orders' => '/orders/', 'Import' => null],
    'actionsHtml' => form_actions('orders-import-form', '/orders/', 'Upload and Preview')]) ?>
<div class="main-content" id="orders-import-content">
    <div class="row"><div class="col-lg-12">
        <form id="orders-import-form" method="post" action="/orders/import" enctype="multipart/form-data" hx-post="/orders/import" hx-encoding="multipart/form-data" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <div class="card" id="orders-import-upload-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Spreadsheet of orders</span><span class="fs-12 fw-normal text-muted">One row per order line. Rows with the same customer and order reference become one order. Nothing is imported until you check the preview and confirm.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'orders-import-errors']) ?>
                    <?= form_row_open($p, 'file', 'File') ?><input type="file" class="form-control<?= invalid_class($errors, 'file') ?>" id="orders-import-form-field-file" name="file" accept=".csv,.xlsx,.xls" required /><?= form_row_close('CSV, XLSX or XLS, up to 5 MB and ' . number_format(ORDER_IMPORT_MAX_ROWS) . ' rows. The first row holds the column headers.') ?>
                    <?= form_select($p, 'future_status', 'Upcoming orders import as', ['confirmed' => 'Confirmed (counted as demand)', 'draft' => 'Draft (not demand until confirmed)'], $futureStatus, $errors, ['help' => 'Rows due before today import as history, fulfilled outside the system. A Status column overrides both.']) ?>
                    <?= form_static($p, 'template', 'Template', '<a href="/orders/import/template" id="orders-import-template-link" download><i class="feather-download me-1"></i>Download the CSV template</a>', true) ?>
                </div>
            </div>
        </form>
        <div class="card" id="orders-import-history-card">
            <div class="card-header"><h5 class="card-title">Imports</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="orders-import-history-table">
                    <thead class="thead-light"><tr><th>Import</th><th>File</th><th>Rows</th><th>Orders</th><th>By</th><th>Uploaded</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($imports as $import): $iid = (int) $import['id']; ?>
                        <tr id="orders-import-row-<?= e($iid) ?>">
                            <td><a <?= nav_attrs('/orders/import/' . $iid) ?>><?= status_dot(ORDER_IMPORT_STATUS_COLORS[$import['status']] ?? 'secondary') ?><?= e($import['number']) ?></a></td>
                            <td><?= e($import['file_name']) ?></td>
                            <td><?= e(number_format((int) $import['rows_total'])) ?></td>
                            <td><?= e(number_format((int) $import['orders_created'])) ?></td>
                            <td><?= e($import['created_by_name']) ?></td>
                            <td><?= e(format_datetime($import['created_at'])) ?></td>
                            <td><?= badge(ORDER_IMPORT_STATUSES[$import['status']] ?? humanize($import['status']), ORDER_IMPORT_STATUS_COLORS[$import['status']] ?? 'secondary') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($imports === []): ?><tr id="orders-import-history-empty"><td colspan="7" class="text-center text-muted py-4">No imports yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</div>
