<?php
declare(strict_types=1);
require_once __DIR__ . '/../items/queries.php';

const ITEM_CLASS_SORTS = ['display_order' => 'c.display_order', 'code' => 'c.code', 'name' => 'c.name', 'kind' => 'c.kind', 'items' => 'item_count'];
const ITEM_CLASS_COLUMNS = 'c.id, c.code, c.name, c.kind, c.purchasable, c.recipe_ingredient, c.display_order, c.is_builtin, c.active, c.notes,
    (SELECT count(*) FROM app.items i WHERE i.item_class = c.code) AS item_count';

function find_item_classes(PDO $pdo, string $search = '', string $sort = 'display_order', int $page = 1, ?string $kind = null): array
{
    $clauses = [];
    $params = [];
    if ($search !== '') {
        $clauses[] = '(c.code ILIKE :s OR c.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($kind !== null && $kind !== '') {
        $clauses[] = 'c.kind = :kind';
        $params['kind'] = $kind;
    }
    $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    return paged_query($pdo,
        'SELECT ' . ITEM_CLASS_COLUMNS . ' FROM app.item_classes c' . $where . ' ORDER BY ' . order_by($sort, ITEM_CLASS_SORTS, 'display_order') . ', c.name',
        'SELECT count(*) FROM app.item_classes c' . $where, $params, $page);
}

function find_item_class(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . ITEM_CLASS_COLUMNS . ' FROM app.item_classes c WHERE c.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_item_class(PDO $pdo, string $code, string $name, string $kind, bool $purchasable, bool $recipeIngredient, int $displayOrder, bool $active, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.item_classes (code, name, kind, purchasable, recipe_ingredient, display_order, active, notes)
        VALUES (:code, :name, :kind, :purchasable, :recipe_ingredient, :display_order, :active, :notes)
        RETURNING id, code, name, kind, purchasable, recipe_ingredient, display_order, is_builtin, active, notes
    SQL);
    $statement->execute(['code' => $code, 'name' => $name, 'kind' => $kind, 'purchasable' => $purchasable ? 't' : 'f', 'recipe_ingredient' => $recipeIngredient ? 't' : 'f',
        'display_order' => $displayOrder, 'active' => $active ? 't' : 'f', 'notes' => $notes]);
    item_class_cache_reset();
    return $statement->fetch();
}

/** Built-in classes keep their code and kind (the database trigger is the backstop). */
function update_item_class(PDO $pdo, int $id, string $code, string $name, string $kind, bool $purchasable, bool $recipeIngredient, int $displayOrder, bool $active, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.item_classes
        SET code = :code, name = :name, kind = :kind, purchasable = :purchasable, recipe_ingredient = :recipe_ingredient,
            display_order = :display_order, active = :active, notes = :notes
        WHERE id = :id
        RETURNING id, code, name, kind, purchasable, recipe_ingredient, display_order, is_builtin, active, notes
    SQL);
    $statement->execute(['id' => $id, 'code' => $code, 'name' => $name, 'kind' => $kind, 'purchasable' => $purchasable ? 't' : 'f', 'recipe_ingredient' => $recipeIngredient ? 't' : 'f',
        'display_order' => $displayOrder, 'active' => $active ? 't' : 'f', 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Item class not found.');
    }
    item_class_cache_reset();
    return $row;
}

/** Deletes a custom class; items.item_class references it, so a class in use fails with a foreign-key error. */
function delete_item_class(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('DELETE FROM app.item_classes WHERE id = :id AND NOT is_builtin');
    $statement->execute(['id' => $id]);
    item_class_cache_reset();
    return $statement->rowCount() > 0;
}
