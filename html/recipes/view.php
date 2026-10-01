<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That recipe version does not exist.');
$version = find_recipe_version($pdo, $id) ?? not_found('That recipe version does not exist.');

// Scale volume is entered in the display volume unit; default to the version's own batch volume.
$scaleRaw = trim((string) ($_GET['scale'] ?? ''));
$scaleDisplay = is_numeric($scaleRaw) && (float) $scaleRaw > 0 ? (float) $scaleRaw : null;
$scaleL = $scaleDisplay !== null ? (float) from_display($scaleDisplay, 'L') : (float) $version['target_batch_volume_l'];
$data = [
    'version' => $version, 'lines' => scale_recipe_lines($pdo, $id, $scaleL), 'scaleL' => $scaleL,
    'scaleDisplay' => $scaleDisplay ?? round((float) to_display($version['target_batch_volume_l'], 'L'), 4),
];
if (is_results_request('recipe-view-lines-table')) {
    header('Vary: HX-Request');
    echo view('recipes/partials/lines-table.php', $data);
    exit;
}
$data += [
    'user' => $user, 'otherVersions' => recipe_version_options($pdo, (int) $version['product_id'], $id),
    'overheadRate' => recipe_overhead_rate($pdo, $version['beverage_type']), 'alert' => null,
];
log_screen_entered('recipe-view', 'recipe_version', $id, $version['product_name'] . ' v' . $version['version_no']);
render_screen($version['product_name'] . ' v' . $version['version_no'], 'recipe-view', view('recipes/partials/view.php', $data), 'recipe_version', $id);
