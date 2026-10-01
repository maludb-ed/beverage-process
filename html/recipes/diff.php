<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';

// Pattern A fragment: the diff of this version against another version of the same product.
$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That recipe version does not exist.');
$version = find_recipe_version($pdo, $id) ?? not_found('That recipe version does not exist.');
$otherId = request_integer('other');
$other = $otherId !== null ? find_recipe_version($pdo, $otherId) : null;
if ($other === null || (int) $other['product_id'] !== (int) $version['product_id']) {
    echo '<p class="text-muted mb-0" id="recipe-view-diff-empty">Choose another version to compare with.</p>';
    exit;
}
echo view('recipes/partials/diff.php', ['diff' => diff_recipe_versions($pdo, $id, (int) $otherId)]);
