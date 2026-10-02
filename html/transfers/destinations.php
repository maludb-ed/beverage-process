<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

// Pattern A fragment: the destination select, limited to the source's tax state, plus fresh lines for the source.
$user = require_role('receiving');
$locations = inventory_locations(db());
$fromId = request_integer('from_location_id');
$from = $fromId !== null ? ($locations[$fromId] ?? null) : null;
$toId = request_integer('to_location_id');
$toOptions = $from !== null ? inventory_location_options($locations, $from['tax_state'], $fromId) : [];
echo view('transfers/partials/to-select.php', ['toOptions' => $toOptions, 'selected' => isset($toOptions[$toId]) ? $toId : '', 'errors' => [], 'hasFrom' => $from !== null]);

// The lines depend on the source too: replace them (out of band) with one blank line
// offering the items in stock at the new source.
$pdo = db();
$n = 'n' . time();
$lines = transfer_prepare_lines($pdo, $from !== null ? $fromId : null, [$n => []]);
echo '<div class="card-body" id="transfer-form-lines" hx-swap-oob="true">'
    . view('transfers/partials/line-row.php', ['n' => $n, 'line' => $lines[$n], 'itemOptions' => $from !== null ? inventory_items_at_location($pdo, $fromId) : [], 'lineErrors' => []])
    . '</div>';
