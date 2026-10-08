<?php /** @var array $rows  @var array $query  @var array $premises  @var bool $canEdit */
$url = '/equipment/';
$actions = list_search('equipment-list', $url, $query['q'], 'Search equipment', '#equipment-list-filter-kind, #equipment-list-filter-status, #equipment-list-filter-premises-id')
    . '<select class="form-select" name="kind" id="equipment-list-filter-kind" aria-label="Kind" hx-get="' . e($url) . '" hx-target="#equipment-list-results" hx-swap="outerHTML" hx-trigger="change" hx-include="#equipment-list-search, #equipment-list-filter-status, #equipment-list-filter-premises-id"><option value="">All kinds</option>';
foreach (EQUIPMENT_KINDS as $code => $label) {
    $actions .= '<option value="' . e($code) . '"' . ($query['kind'] === $code ? ' selected' : '') . '>' . e($label) . '</option>';
}
$actions .= '</select>'
    . '<select class="form-select" name="status" id="equipment-list-filter-status" aria-label="Status" hx-get="' . e($url) . '" hx-target="#equipment-list-results" hx-swap="outerHTML" hx-trigger="change" hx-include="#equipment-list-search, #equipment-list-filter-kind, #equipment-list-filter-premises-id"><option value="">All statuses</option>';
foreach (EQUIPMENT_STATUSES as $code => $label) {
    $actions .= '<option value="' . e($code) . '"' . ($query['status'] === $code ? ' selected' : '') . '>' . e($label) . '</option>';
}
$actions .= '</select>';
if (count($premises) > 1) {
    $actions .= '<select class="form-select" name="premises_id" id="equipment-list-filter-premises-id" aria-label="Premises" hx-get="' . e($url) . '" hx-target="#equipment-list-results" hx-swap="outerHTML" hx-trigger="change" hx-include="#equipment-list-search, #equipment-list-filter-kind, #equipment-list-filter-status"><option value="">All premises</option>';
    foreach ($premises as $pid => $name) {
        $actions .= '<option value="' . e($pid) . '"' . ((string) ($query['premises_id'] ?? '') === (string) $pid ? ' selected' : '') . '>' . e($name) . '</option>';
    }
    $actions .= '</select>';
} else {
    $actions .= '<input type="hidden" name="premises_id" id="equipment-list-filter-premises-id" value="" />';
}
$actions .= nav_button('equipment-list-schedule-btn', '/schedule/?kind=equipment', 'Schedule', 'feather-calendar', 'btn btn-light-brand')
    . ($canEdit ? nav_button('equipment-list-add-btn', '/equipment/new', 'Add Equipment') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Equipment', 'screen' => 'equipment-list', 'crumbs' => ['Setup' => null, 'Equipment' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="equipment-list-content">
    <?= view('equipment/partials/cards.php', ['rows' => $rows, 'query' => $query, 'canEdit' => $canEdit]) ?>
</div>
