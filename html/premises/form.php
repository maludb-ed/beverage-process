<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_role();
$id = request_integer('id');
if ($id !== null) {
    $premises = find_premises(db(), $id) ?? not_found('That premises does not exist.');
    $screen = 'premises-edit';
} else {
    $premises = ['name' => request_string('name', 120), 'kind' => in_options(request_string('kind'), PREMISES_KINDS) ? request_string('kind') : 'bonded_winery'];
    $screen = 'premises-add';
}
log_screen_entered($screen, 'premises', $id, $premises['name'] ?? null);
render_screen($id ? 'Edit Premises' : 'Add Premises', $screen, view('premises/partials/form.php', ['premises' => $premises, 'errors' => []]), 'premises', $id);
