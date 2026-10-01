<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/specs/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That spec does not exist.');
$spec = find_spec($pdo, $id) ?? not_found('That spec does not exist.');
$label = $spec['product_name'] . ' ' . $spec['stage_code'] . ' ' . $spec['measurement_type_code'];
try {
    $pdo->beginTransaction();
    $deleted = delete_spec($pdo, $id);
    log_activity($pdo, $deleted ? 'spec_deleted' : 'spec_updated', 'spec', $id, $label, $spec, $deleted ? null : ['active' => false], ['reason' => $deleted ? 'deleted' : 'referenced by readings, deactivated'], 'specs-list');
    $pdo->commit();
    flash('success', $deleted ? 'Spec deleted.' : 'Readings use this spec, so it was deactivated instead of deleted.');
    hx_trigger('specsChanged');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The spec could not be deleted.');
}
hx_location('/products/' . (int) $spec['product_id'] . '/specs');
