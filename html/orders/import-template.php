<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

// The CSV template: a plain download, never an HTMX swap.
$user = require_role('sales');
log_screen_entered('orders-import-template');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="customer-orders-template.csv"');
$out = fopen('php://output', 'wb');
foreach (orders_import_template_rows() as $row) {
    fputcsv($out, $row, ',', '"', '');
}
fclose($out);
