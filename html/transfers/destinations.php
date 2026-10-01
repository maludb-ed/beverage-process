<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

// Pattern A fragment: the destination select, limited to the source's tax state.
$user = require_role('receiving');
$locations = inventory_locations(db());
$fromId = request_integer('from_location_id');
$from = $fromId !== null ? ($locations[$fromId] ?? null) : null;
$toId = request_integer('to_location_id');
$toOptions = $from !== null ? inventory_location_options($locations, $from['tax_state'], $fromId) : [];
echo view('transfers/partials/to-select.php', ['toOptions' => $toOptions, 'selected' => isset($toOptions[$toId]) ? $toId : '', 'errors' => [], 'hasFrom' => $from !== null]);
