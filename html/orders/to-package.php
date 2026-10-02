<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

// The packaging queue: open order lines by format, oldest due first, with what stock already covers.
$user = require_login();
$pdo = db();
$formats = [];
foreach (orders_formats_with_open_lines($pdo) as $configId => $format) {
    $lines = orders_format_needs($pdo, $configId);
    $format['lines'] = $lines;
    $format['need'] = array_sum(array_column($lines, 'need'));
    $format['on_hand'] = orders_released_units($pdo, $configId);
    $formats[$configId] = $format;
}
log_screen_entered('orders-to-package');
render_screen('Packaging queue', 'orders-to-package', view('orders/to-package.php', ['formats' => $formats, 'canPackage' => user_can($user, 'sales', 'production')]));
