<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// Pattern A fragment: the "add a lot that was not expected" form for the count sheet.
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');
$count = find_count_line_owner($pdo, $id) ?? not_found('That count does not exist.');
echo view('counts/partials/line-add-form.php', ['count' => $count, 'itemOptions' => item_options($pdo), 'lots' => [], 'input' => [], 'errors' => []]);
