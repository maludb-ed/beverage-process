<?php /** @var array $result  @var array $query  @var array $counts  @var array $customers  @var bool $canEdit */
$actions = list_search('kegs-list', '/kegs/', $query['q'], 'Search serials, lots, holders', '#kegs-list-filter-state, #kegs-list-filter-customer-id, #kegs-list-filter-older-than-days')
    . list_filter('kegs-list', 'state', '/kegs/', KEG_STATES, $query['state'] ?? '', 'All states')
    . list_filter('kegs-list', 'customer_id', '/kegs/', $customers, $query['customer_id'] ?? '', 'All holders')
    . list_filter('kegs-list', 'older_than_days', '/kegs/', ['30' => 'Not moved in 30 days', '60' => 'Not moved in 60 days', '90' => 'Not moved in 90 days'], $query['older_than_days'] ?? '', 'Any age')
    . ($canEdit ? nav_button('kegs-list-return-btn', '/kegs/return', 'Return Kegs', 'feather-corner-down-left', 'btn btn-light-brand') . nav_button('kegs-list-add-btn', '/kegs/new', 'Add Keg') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Kegs', 'screen' => 'kegs-list', 'crumbs' => ['Packaging' => null, 'Kegs' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="kegs-list-content">
    <div class="row mb-4" id="kegs-list-tiles">
        <?php foreach (KEG_STATES as $state => $label): ?>
            <div class="col-6 col-md-4 col-xl">
                <a class="card mb-3 text-reset" id="kegs-list-tile-<?= e(str_replace('_', '-', $state)) ?>" <?= nav_attrs('/kegs/' . query_string(['state' => $state])) ?>>
                    <div class="card-body py-3">
                        <div class="fs-12 text-muted mb-1"><?= status_dot(status_color($state)) ?><?= e($label) ?></div>
                        <div class="fs-4 fw-bold" id="kegs-list-tile-<?= e(str_replace('_', '-', $state)) ?>-count"><?= e($counts[$state] ?? 0) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="row">
        <?= view('kegs/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
