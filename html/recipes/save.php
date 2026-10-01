<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$volumeDisplay = post_decimal('batch_volume');
$changeNote = request_string('change_note', 2000);

// ---- Create a draft version (recipe-add) -------------------------------------------
if ($id === null) {
    $productId = request_integer('product_id') ?? not_found('That product does not exist.');
    $product = find_product($pdo, $productId) ?? not_found('That product does not exist.');
    $copyFrom = request_integer('copy_from_version_id');
    $versionOptions = recipe_version_options($pdo, $productId);
    $errors = [];
    if ($volumeDisplay === null || $volumeDisplay === false || $volumeDisplay <= 0) { $errors['batch_volume'] = 'Enter a batch volume above zero.'; }
    if ($copyFrom !== null && !isset($versionOptions[$copyFrom])) { $errors['copy_from'] = 'Choose a version of this product.'; }
    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $created = insert_recipe_version($pdo, $productId, (float) from_display($volumeDisplay, 'L'), $copyFrom, $changeNote ?: null, (int) $user['id']);
            log_activity($pdo, 'recipe_version_created', 'recipe_version', (int) $created['id'], $product['name'] . ' v' . $created['version_no'], null, $created, [], 'recipe-add');
            $pdo->commit();
            flash('success', 'Draft version ' . $created['version_no'] . ' created for ' . $product['name'] . '.');
            hx_trigger('recipesChanged');
            hx_location('/recipes/' . $created['id'] . '/edit');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The version could not be created.';
        }
    }
    http_response_code(422);
    render_screen('New recipe version', 'recipe-add', view('recipes/partials/form.php', [
        'product' => $product, 'errors' => $errors, 'versionOptions' => $versionOptions,
        'input' => ['batch_volume' => $volumeDisplay === false ? request_string('batch_volume', 20) : $volumeDisplay, 'copy_from_version_id' => $copyFrom, 'change_note' => $changeNote],
    ]), 'product', $productId);
    exit;
}

// ---- Save a draft's header, stages and lines (recipe-edit) -------------------------
$before = find_recipe_version($pdo, $id) ?? not_found('That recipe version does not exist.');
$label = $before['product_name'] . ' v' . $before['version_no'];
if ($before['status'] !== 'draft') {
    log_screen_entered('recipe-view', 'recipe_version', $id, $label);
    http_response_code(409);
    render_screen($label, 'recipe-view', view('recipes/partials/view.php', array_merge(recipe_view_data($pdo, $before, $user), ['alert' => 'Version ' . $before['version_no'] . ' is ' . $before['status'] . '; create a new version.'])), 'recipe_version', $id);
    exit;
}
$stageOptions = product_stage_options($pdo, $before['beverage_type']);
$catalog = recipe_item_catalog($pdo, array_map('intval', array_column($before['lines'], 'item_id')));
$errors = [];
if ($volumeDisplay === null || $volumeDisplay === false || $volumeDisplay <= 0) { $errors['batch_volume'] = 'Enter a batch volume above zero.'; }
[$stages, $stageErrors] = validate_recipe_stages(is_array($_POST['stages'] ?? null) ? $_POST['stages'] : [], $stageOptions);
[$lines, $lineErrors] = validate_recipe_lines(is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [], $catalog, array_values(array_unique(array_column($stages, 'stage_code'))));
if ($stageErrors !== []) { $errors['stages'] = 'Fix the highlighted stages.'; }
if ($lineErrors !== []) { $errors['lines'] = 'Fix the highlighted lines.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = update_recipe_version($pdo, $id, (float) from_display($volumeDisplay, 'L'), $changeNote ?: null);
        replace_recipe_stages($pdo, $id, $stages);
        replace_recipe_lines($pdo, $id, $lines);
        $after = $saved + ['stages' => array_values($stages), 'lines' => array_values(array_map(static fn(array $l) => array_diff_key($l, ['qty' => 1, 'basis' => 1]), $lines))];
        log_activity($pdo, 'recipe_version_updated', 'recipe_version', $id, $label,
            ['target_batch_volume_l' => $before['target_batch_volume_l'], 'change_note' => $before['change_note'], 'stage_count' => count($before['stages']), 'line_count' => count($before['lines'])],
            $after, ['stage_count' => count($stages), 'line_count' => count($lines)], 'recipe-edit');
        $pdo->commit();
        flash('success', $label . ' saved.');
        hx_trigger('recipesChanged');
        hx_location('/recipes/' . $id);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The version could not be saved.') : $exception->getMessage();
    }
}
$before['volume'] = $volumeDisplay === false ? request_string('batch_volume', 20) : $volumeDisplay;
$before['change_note'] = $changeNote;
http_response_code(422);
render_screen('Edit ' . $label, 'recipe-edit', view('recipes/partials/editor.php', [
    'version' => $before, 'stages' => $stages, 'lines' => $lines, 'errors' => $errors, 'stageErrors' => $stageErrors, 'lineErrors' => $lineErrors,
    'catalog' => $catalog, 'stageOptions' => $stageOptions, 'stageNames' => recipe_stage_names($pdo),
]), 'recipe_version', $id);
