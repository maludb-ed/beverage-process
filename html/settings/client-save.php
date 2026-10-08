<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

require_post();
verify_csrf();
$user = require_role();

$pdo = db();
$before = find_client_settings($pdo);
$volumeUnits = settings_unit_options($pdo, 'volume', true);
$massUnits = settings_unit_options($pdo, 'mass');
$input = $before;
$input['client_name'] = request_string('client_name', 120);
$input['timezone'] = request_string('timezone', 64);
$input['volume_display_unit'] = request_string('volume_display_unit', 10);
$input['mass_display_unit'] = request_string('mass_display_unit', 10);
$input['fruit_display_unit'] = request_string('fruit_display_unit', 10);
$input['equipment_double_booking'] = post_bool('equipment_double_booking');

$errors = [];
if ($input['client_name'] === '') { $errors['client_name'] = 'Client name is required.'; }
if (!in_array($input['timezone'], DateTimeZone::listIdentifiers(), true)) { $errors['timezone'] = 'Choose a time zone.'; }
if (!in_options($input['volume_display_unit'], $volumeUnits)) { $errors['volume_display_unit'] = 'Choose a volume unit.'; }
if (!in_options($input['mass_display_unit'], $massUnits)) { $errors['mass_display_unit'] = 'Choose a mass unit.'; }
if (!in_options($input['fruit_display_unit'], $massUnits)) { $errors['fruit_display_unit'] = 'Choose a fruit weight unit.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = update_client_settings($pdo, $input['client_name'], $input['timezone'], $input['volume_display_unit'], $input['mass_display_unit'], $input['fruit_display_unit']);
        set_double_booking($pdo, $input['equipment_double_booking']);
        $saved['equipment_double_booking'] = $input['equipment_double_booking'];
        $before['equipment_double_booking'] = (bool) $before['equipment_double_booking'];
        $fields = ['client_name', 'timezone', 'volume_display_unit', 'mass_display_unit', 'fruit_display_unit', 'equipment_double_booking'];
        log_activity($pdo, 'client_settings_updated', 'client_settings', 1, $saved['client_name'],
            array_intersect_key($before, array_flip($fields)), array_intersect_key($saved, array_flip($fields)), [], 'settings-client');
        $pdo->commit();
        flash('success', 'Client settings saved.');
        hx_trigger('clientSettingsChanged');
        hx_location('/settings/client');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The settings could not be saved.';
    }
}
http_response_code(422);
render_screen('Client settings', 'settings-client', view('settings/client-page.php', ['settings' => $input, 'errors' => $errors, 'pdo' => $pdo]), 'client_settings', 1);
