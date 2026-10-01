<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// A count has no edit form: its sheet is edited on the view. /counts/new only.
$user = require_role('receiving');
$pdo = db();
if (request_integer('id') !== null) {
    hx_location('/counts/' . request_integer('id'));
}
// Prefill: ?location=<name>&kind=cycle|physical.
$resolved = inventory_resolve_prefill($pdo, '', '', request_string('location', 80));
$kind = request_string('kind', 20);
$count = ['location_id' => $resolved['location_id'], 'kind' => in_options($kind, COUNT_KINDS) ? $kind : 'cycle'];
log_screen_entered('count-add');
render_screen('Start Count', 'count-add', view('counts/partials/form.php', ['count' => $count, 'errors' => [], 'locations' => inventory_locations($pdo)]));
