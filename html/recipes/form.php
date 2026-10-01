<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That recipe version does not exist.');
$version = find_recipe_version($pdo, $id) ?? not_found('That recipe version does not exist.');
$label = $version['product_name'] . ' v' . $version['version_no'];
if ($version['status'] !== 'draft') {
    // Only drafts are editable: show the version with an alert (409). The trigger recipe_guard_immutable is the backstop.
    log_screen_entered('recipe-view', 'recipe_version', $id, $label);
    http_response_code(409);
    render_screen($label, 'recipe-view', view('recipes/partials/view.php', array_merge(recipe_view_data($pdo, $version, $user), ['alert' => 'Version ' . $version['version_no'] . ' is ' . $version['status'] . '; create a new version.'])), 'recipe_version', $id);
    exit;
}
$stages = [];
foreach ($version['stages'] as $stage) {
    $stages[(string) $stage['id']] = $stage;
}
$lines = [];
foreach ($version['lines'] as $line) {
    $lines[(string) $line['id']] = recipe_qty_to_display($line) + ['seq' => $line['seq'], 'item_id' => (int) $line['item_id'], 'stage_code' => $line['stage_code'],
        'purpose' => $line['purpose'], 'consumption_mode' => $line['consumption_mode'], 'notes' => $line['notes']];
}
$version['volume'] = round((float) to_display($version['target_batch_volume_l'], 'L'), 4);
log_screen_entered('recipe-edit', 'recipe_version', $id, $label);
render_screen('Edit ' . $label, 'recipe-edit', view('recipes/partials/editor.php', [
    'version' => $version, 'stages' => $stages, 'lines' => $lines, 'errors' => [], 'stageErrors' => [], 'lineErrors' => [],
    'catalog' => recipe_item_catalog($pdo, array_map('intval', array_column($version['lines'], 'item_id'))),
    'stageOptions' => product_stage_options($pdo, $version['beverage_type']), 'stageNames' => recipe_stage_names($pdo),
]), 'recipe_version', $id);
