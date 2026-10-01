<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';

$user = require_role('quality');
$pdo = db();
$input = ['target_kind' => 'batch', 'target_id' => null, 'measurement_type_code' => request_string('measurement', 30), 'value' => request_string('value', 20),
    'taken_at' => (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i'), 'stage_code' => '', 'method' => '', 'note' => ''];
if (request_integer('lot') !== null) { $input['target_kind'] = 'lot'; $input['target_id'] = request_integer('lot'); }
elseif (request_integer('batch') !== null) { $input['target_id'] = request_integer('batch'); }
log_screen_entered('lab-reading-add');
render_screen('Record reading', 'lab-reading-add', view('lab/partials/form.php', lab_form_data($pdo, $input, [])));
