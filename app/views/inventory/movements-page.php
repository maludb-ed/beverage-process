<?php /** @var array $result  @var array $query  @var array $locations */
$include = '#inventory-movements-filter-txn-type, #inventory-movements-filter-location-id, #inventory-movements-filter-date-from, #inventory-movements-filter-date-to, #inventory-movements-filter-item-id, #inventory-movements-filter-lot-id';
$hidden = static fn(string $name, $value): string => '<input type="hidden" name="' . e($name) . '" id="inventory-movements-filter-' . e(str_replace('_', '-', $name)) . '" value="' . e($value) . '" />';
$date = static fn(string $name, string $label, $value): string => '<input type="date" class="form-control" name="' . e($name) . '" id="inventory-movements-filter-' . e(str_replace('_', '-', $name)) . '" value="' . e($value) . '" aria-label="' . e($label) . '"'
    . ' hx-get="/inventory/movements" hx-target="#inventory-movements-results" hx-swap="outerHTML" hx-trigger="change" hx-include="' . e($include) . '" />';
$actions = $hidden('item_id', $query['item_id']) . $hidden('lot_id', $query['lot_id'])
    . view('inventory/partials/filter.php', ['screen' => 'inventory-movements', 'name' => 'txn_type', 'url' => '/inventory/movements', 'options' => INVENTORY_TXN_TYPES, 'selected' => $query['txn_type'], 'allLabel' => 'All types', 'include' => $include])
    . view('inventory/partials/filter.php', ['screen' => 'inventory-movements', 'name' => 'location_id', 'url' => '/inventory/movements', 'options' => $locations, 'selected' => (string) ($query['location_id'] ?? ''), 'allLabel' => 'All locations', 'include' => $include])
    . $date('date_from', 'From date', $query['date_from']) . $date('date_to', 'To date', $query['date_to']);
?>
<?= view('shared/page-header.php', ['title' => 'Movements', 'screen' => 'inventory-movements', 'crumbs' => ['Inventory' => null, 'Movements' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="inventory-movements-content">
    <div class="row">
        <?= view('inventory/partials/movements-table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
