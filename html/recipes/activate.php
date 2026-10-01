<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That recipe version does not exist.');
$version = find_recipe_version($pdo, $id) ?? not_found('That recipe version does not exist.');
$label = $version['product_name'] . ' v' . $version['version_no'];
try {
    $pdo->beginTransaction();
    $after = activate_recipe_version($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'recipe_version_activated', 'recipe_version', $id, $label,
        ['status' => $version['status'], 'previous_active' => $after['previous_active']], $after, [], 'recipe-view');
    $pdo->commit();
    flash('success', $label . ' is now the active recipe.' . ($after['overhead_included'] ? '' : ' Overhead was not included in its standard cost.'));
    hx_trigger('recipesChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The version could not be activated.'));
}
hx_location('/recipes/' . $id);
