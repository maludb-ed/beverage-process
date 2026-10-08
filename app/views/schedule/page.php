<?php /** @var string $view  @var array $resources  @var array $bookings  @var array $occupants  @var array $query  @var DateTimeImmutable $gridStart  @var DateTimeImmutable $gridEnd  @var string $today  @var array $premises  @var bool $canEdit  @var array $catalog */
$url = '/schedule/';
$inc = '#schedule-filter-view, #schedule-filter-premises-id, #schedule-filter-kind, #schedule-filter-resource, #schedule-filter-from, #schedule-filter-weeks, #schedule-filter-month';
$select = static function (string $name, array $options, ?string $selected, string $allLabel, string $aria) use ($url, $inc): string {
    $html = '<select class="form-select" name="' . e($name) . '" id="schedule-filter-' . e(str_replace('_', '-', $name)) . '" aria-label="' . e($aria) . '" hx-get="' . e($url) . '" hx-target="#schedule-results" hx-swap="outerHTML" hx-trigger="change" hx-include="' . e($inc) . '">';
    if ($allLabel !== '') { $html .= '<option value="">' . e($allLabel) . '</option>'; }
    foreach ($options as $value => $label) {
        if (is_array($label)) {
            $html .= '<optgroup label="' . e($value) . '">';
            foreach ($label as $v => $l) { $html .= '<option value="' . e($v) . '"' . ((string) $selected === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
            $html .= '</optgroup>';
        } else {
            $html .= '<option value="' . e($value) . '"' . ((string) $selected === (string) $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
    }
    return $html . '</select>';
};
$kindOptions = ['vessel' => 'All vessels', 'equipment' => 'All equipment', 'Vessel kinds' => VESSEL_KINDS, 'Equipment kinds' => EQUIPMENT_KINDS];
$actions = '<input type="hidden" name="view" id="schedule-filter-view" value="' . e($query['view'] ?? '') . '" />'
    . '<input type="hidden" name="month" id="schedule-filter-month" value="' . e($query['month']) . '" />';
if (count($premises) > 1) {
    $actions .= $select('premises_id', $premises, $query['premises_id'] === null ? '' : (string) $query['premises_id'], 'All premises', 'Premises');
} else {
    $actions .= '<input type="hidden" name="premises_id" id="schedule-filter-premises-id" value="" />';
}
$actions .= $select('kind', $kindOptions, $query['kind'] ?? '', 'Vessels and equipment', 'Kind')
    . $select('resource', reservation_resource_groups($catalog), $query['resource'] ?? '', 'Every resource', 'Resource');
if ($view === 'month') {
    $actions .= '<input type="hidden" name="from" id="schedule-filter-from" value="' . e($query['from']) . '" /><input type="hidden" name="weeks" id="schedule-filter-weeks" value="' . e($query['weeks']) . '" />';
} else {
    $actions .= '<div class="input-group"><span class="input-group-text">From</span><input type="date" class="form-control" name="from" id="schedule-filter-from" value="' . e($query['from']) . '" aria-label="From" hx-get="' . e($url) . '" hx-target="#schedule-results" hx-swap="outerHTML" hx-trigger="change" hx-include="' . e($inc) . '" /></div>'
        . $select('weeks', SCHEDULE_WEEKS, (string) $query['weeks'], '', 'Weeks');
}
$toggleQuery = array_merge($query, ['view' => $view === 'month' ? null : 'month']);
$actions .= nav_button('schedule-toggle-view-btn', $url . query_string($toggleQuery), $view === 'month' ? 'Timeline' : 'Month', $view === 'month' ? 'feather-align-left' : 'feather-grid', 'btn btn-light-brand');
if ($canEdit) {
    $actions .= nav_button('schedule-reserve-btn', '/reservations/new?on=' . e($today) . ($query['resource'] !== null ? '&resource=' . e($query['resource']) : ''), 'Reserve', 'feather-bookmark');
}
?>
<?= view('shared/page-header.php', ['title' => 'Equipment schedule', 'screen' => 'equipment-schedule', 'crumbs' => ['Production' => null, 'Equipment schedule' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="schedule-content">
    <div class="row">
        <?= view('schedule/partials/' . $view . '.php', ['resources' => $resources, 'bookings' => $bookings, 'occupants' => $occupants, 'query' => $query, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'today' => $today, 'canEdit' => $canEdit, 'catalog' => $catalog]) ?>
    </div>
</div>
