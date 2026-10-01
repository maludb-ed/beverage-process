<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';

// Pattern A fragment: the target select (and the stage select for lab readings) for the chosen target kind.
$user = require_role('quality');
$pdo = db();
$prefix = request_string('prefix', 30) === 'sensory-form' ? 'sensory-form' : 'lab-reading-form';
$kind = request_string('target_kind', 10) === 'lot' ? 'lot' : 'batch';
$input = ['target_kind' => $kind, 'target_id' => request_integer('target_id'), 'stage_code' => ''];
$data = lab_form_data($pdo, $input, []);
echo view('lab/partials/target-block.php', ['prefix' => $prefix, 'kind' => $kind, 'input' => $input, 'errors' => [], 'targets' => $data['targets'],
    'stages' => $prefix === 'lab-reading-form' ? $data['stages'] : [], 'stageDefault' => $data['stageDefault']]);
