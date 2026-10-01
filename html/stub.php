<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = require_login();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$item = navigation_item_for_path($path);
if ($item === null) {
    not_found('That page does not exist.');
}
log_screen_entered($item['screen']);
render_screen($item['label'], $item['screen'], view('stub/page.php', ['item' => $item]));
