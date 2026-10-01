<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/trace/queries.php';

// Pattern A autocomplete: <option>s for the lot or batch number datalist.
$user = require_login();
$field = request_string('field', 10) === 'batch' ? 'batch' : 'lot';
$q = request_string($field === 'batch' ? 'batch_number' : 'lot_number', 60);
$values = mb_strlen($q) >= 2 ? ($field === 'batch' ? suggest_batch_numbers(db(), $q) : suggest_lot_numbers(db(), $q)) : [];
header('Vary: HX-Request');
foreach ($values as $value) {
    echo '<option value="' . e($value) . '"></option>';
}
