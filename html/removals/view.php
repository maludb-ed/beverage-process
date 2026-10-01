<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That removal does not exist.');
$removal = find_removal($pdo, $id) ?? not_found('That removal does not exist.');
$lines = find_removal_lines($pdo, $id);
$tax = compute_removal_tax($pdo, (int) $removal['premises_id'], $lines,
    (new DateTimeImmutable((string) $removal['removed_at']))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d'));
log_screen_entered('removal-view', 'removal', $id, $removal['number']);
render_screen($removal['number'], 'removal-view', view('removals/partials/view.php', [
    'removal' => $removal, 'lines' => $lines, 'tax' => $tax, 'user' => $user, 'reverseError' => null,
]), 'removal', $id);
