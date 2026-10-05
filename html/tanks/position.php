<?php
declare(strict_types=1);
/** POST /tanks/{id}/position — where a vessel stands on the Tank view, in grid units (x, y). Production (or owner). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/tanks/queries.php';

require_post();
verify_csrf();
$user = require_role('production');

$pdo = db();
$id = request_integer('id') ?? not_found('That vessel does not exist.');
$before = find_tank_view_vessel($pdo, $id) ?? not_found('That vessel does not exist.');
$x = request_integer('x');
$y = request_integer('y');
if ($x === null || $y === null || $x < 0 || $y < 0 || $x > 400 || $y > 400) {
    http_response_code(422);
    echo view('shared/error.php', ['message' => 'Give the position as x and y grid units between 0 and 400.']);
    exit;
}
$pdo->beginTransaction();
$vessel = update_vessel_position($pdo, $id, $x, $y);
log_activity($pdo, 'tank_position_set', 'vessel', (int) $vessel['id'], $vessel['name'],
    ['board_x' => $before['board_x'], 'board_y' => $before['board_y']], ['board_x' => $x, 'board_y' => $y], [], 'tank-view');
$pdo->commit();
// A drag already shows the card where it was dropped; nothing to swap, no refresh event (the board would jump).
http_response_code(204);
