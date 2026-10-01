<?php /** @var array $result  @var array $query  @var array $customers  @var bool $canEdit */
$s = 'removals-list';
$filterIds = ['#removals-list-search', '#removals-list-filter-destination-kind', '#removals-list-filter-customer-id', '#removals-list-filter-status', '#removals-list-filter-date-from', '#removals-list-filter-date-to'];
$includeExcept = static fn(string $self): string => implode(', ', array_filter($filterIds, static fn($i) => $i !== $self));
$filter = static function (string $name, array $options, $selected, string $all) use ($s, $includeExcept): string {
    $html = list_filter($s, $name, '/removals/', $options, (string) ($selected ?? ''), $all);
    return str_replace('hx-include="#removals-list-search"', 'hx-include="' . e($includeExcept('#removals-list-filter-' . str_replace('_', '-', $name))) . '"', $html);
};
$date = static fn(string $name, string $label, $value): string => '<input type="date" class="form-control" name="' . e($name) . '" id="removals-list-filter-' . e(str_replace('_', '-', $name)) . '" value="' . e($value) . '" aria-label="' . e($label) . '" title="' . e($label) . '"'
    . ' hx-get="/removals/" hx-target="#removals-list-results" hx-swap="outerHTML" hx-trigger="change" hx-include="' . e($includeExcept('#removals-list-filter-' . str_replace('_', '-', $name))) . '" />';
$actions = list_search($s, '/removals/', $query['q'], 'Search number, customer, reference', $includeExcept('#removals-list-search'))
    . $filter('destination_kind', REMOVAL_DESTINATIONS, $query['destination_kind'], 'All destinations')
    . $filter('customer_id', $customers, $query['customer_id'], 'All customers')
    . $filter('status', REMOVAL_STATUSES, $query['status'], 'All statuses')
    . $date('date_from', 'From date', $query['date_from']) . $date('date_to', 'To date', $query['date_to'])
    . ($canEdit ? nav_button('removals-list-return-btn', '/removals/new?direction=in', 'Add Return', 'feather-log-in', 'btn btn-light-brand') . nav_button('removals-list-add-btn', '/removals/new', 'Add Removal') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Removals', 'screen' => 'removals-list', 'crumbs' => ['Compliance' => null, 'Removals' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="removals-list-content">
    <div class="row">
        <?= view('removals/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
