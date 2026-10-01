<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sensory/queries.php';

$user = require_role('quality');
$input = ['target_kind' => 'batch', 'target_id' => request_integer('batch'), 'panel_on' => today(), 'panelist_name' => $user['display_name'], 'sample_code' => '', 'verdict' => '',
    'attributes' => [], 'faults' => [], 'comment' => ''];
if (request_integer('lot') !== null) { $input['target_kind'] = 'lot'; $input['target_id'] = request_integer('lot'); }
log_screen_entered('sensory-add');
render_screen('Record sensory panel', 'sensory-add', view('sensory/partials/form.php', ['input' => $input, 'errors' => [], 'targets' => find_reading_targets(db(), $input['target_kind'])]));
