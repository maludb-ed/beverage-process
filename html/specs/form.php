<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/specs/queries.php';

$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That spec does not exist.');
$spec = find_spec($pdo, $id) ?? not_found('That spec does not exist.');
log_screen_entered('spec-edit', 'spec', $id, $spec['product_name'] . ' ' . $spec['stage_code'] . ' ' . $spec['measurement_type_code']);
render_screen('Edit Spec', 'spec-edit', view('specs/partials/form.php', [
    'spec' => $spec, 'errors' => [], 'stages' => product_stage_options($pdo, $spec['beverage_type']), 'measurements' => spec_measurement_options($pdo),
]), 'spec', $id);
