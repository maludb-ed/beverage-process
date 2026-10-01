<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// Pattern A fragment: every lot of the chosen item.
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');
$count = find_count_line_owner($pdo, $id) ?? not_found('That count does not exist.');
$itemId = request_integer('item_id');
echo view('counts/partials/line-add-lots.php', ['lots' => $itemId ? find_count_lots($pdo, $itemId, (int) $count['location_id']) : [], 'selected' => '']);
