<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

// Pattern C: fill, clean, mark_lost, found and retire (event parameter); returns only the state card.
// Manifest endpoints /kegs/{id}/fill|clean|lost|found|retire map here with event=.
require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That keg does not exist.');
$keg = find_keg($pdo, $id) ?? not_found('That keg does not exist.');
$event = request_string('event', 20);
$events = ['fill' => 'keg_filled', 'clean' => 'keg_cleaned', 'mark_lost' => 'keg_marked_lost', 'found' => 'keg_found', 'retire' => 'keg_retired'];
$labels = ['fill' => 'filled', 'clean' => 'cleaned', 'mark_lost' => 'marked lost', 'found' => 'marked found', 'retire' => 'retired'];
$errors = [];
$input = ['finished_lot' => request_integer('finished_lot')];
$lots = keg_fill_lot_options($pdo);
if (!isset($events[$event])) { $errors['event'] = 'Choose what to do with the keg.'; }
if ($event === 'fill' && ($input['finished_lot'] === null || !isset($lots[$input['finished_lot']]))) { $errors['finished_lot'] = 'Choose a keg lot with units available.'; }
$notice = null;
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $updated = transition_keg($pdo, $id, $event, $event === 'fill' ? $input['finished_lot'] : null, null, in_array($event, ['found'], true) ? keg_default_location_id($pdo) : null, (int) $user['id'], null);
        log_activity($pdo, $events[$event], 'keg', $id, $keg['serial'], ['state' => $keg['state'], 'current_lot_id' => $keg['current_lot_id']],
            ['state' => $updated['state'], 'current_lot_id' => $updated['current_lot_id']], $event === 'fill' ? ['finished_lot' => $lots[$input['finished_lot']] ?? null] : [], 'keg-view');
        $pdo->commit();
        hx_trigger('kegsChanged');
        $keg = $updated;
        $notice = 'Keg ' . $keg['serial'] . ' ' . $labels[$event] . '.';
        $input = [];
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('keg state failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The keg could not be changed.') : $exception->getMessage();
        $keg = find_keg($pdo, $id) ?? $keg;
    }
}
if ($errors !== []) { http_response_code(422); }
echo view('kegs/partials/state-card.php', [
    'keg' => $keg, 'movements' => find_keg_movements($pdo, $id), 'lots' => keg_fill_lot_options($pdo), 'canEdit' => true, 'errors' => $errors, 'notice' => $notice, 'input' => $input,
]);
