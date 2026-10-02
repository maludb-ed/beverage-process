<?php
declare(strict_types=1);

/**
 * Sidebar navigation, derived from the screen registry in docs/05-action-manifest.md.
 * Each item: screen id (nav id = nav-{screen}), label, canonical URL, slice number.
 * Features whose slice has not shipped render the stub page (html/stub.php).
 */
function navigation_groups(): array
{
    return [
        ['label' => 'Overview', 'icon' => 'feather-airplay', 'items' => [
            ['screen' => 'dashboard', 'label' => 'Dashboard', 'url' => '/', 'slice' => 0],
            ['screen' => 'ama', 'label' => 'Ask me anything', 'url' => '/ama/', 'slice' => 0],
            ['screen' => 'activity-list', 'label' => 'Activity', 'url' => '/activity/', 'slice' => 0],
        ]],
        ['label' => 'Receiving', 'icon' => 'feather-truck', 'items' => [
            ['screen' => 'purchase-orders-list', 'label' => 'Purchase orders', 'url' => '/purchase-orders/', 'slice' => 2],
            ['screen' => 'receipts-list', 'label' => 'Receipts', 'url' => '/receipts/', 'slice' => 2],
            ['screen' => 'lots-list', 'label' => 'Lots', 'url' => '/lots/', 'slice' => 2],
        ]],
        ['label' => 'Inventory', 'icon' => 'feather-package', 'items' => [
            ['screen' => 'inventory-list', 'label' => 'On hand', 'url' => '/inventory/', 'slice' => 3],
            ['screen' => 'inventory-movements', 'label' => 'Movements', 'url' => '/inventory/movements', 'slice' => 3],
            ['screen' => 'reorder-list', 'label' => 'Reorder', 'url' => '/inventory/reorder', 'slice' => 3],
            ['screen' => 'rack-board', 'label' => 'Rack board', 'url' => '/racks/', 'slice' => 3],
            ['screen' => 'transfers-list', 'label' => 'Transfers', 'url' => '/transfers/', 'slice' => 3],
            ['screen' => 'adjustments-list', 'label' => 'Adjustments', 'url' => '/adjustments/', 'slice' => 3],
            ['screen' => 'counts-list', 'label' => 'Counts', 'url' => '/counts/', 'slice' => 3],
        ]],
        ['label' => 'Products', 'icon' => 'feather-book-open', 'items' => [
            ['screen' => 'products-list', 'label' => 'Products and recipes', 'url' => '/products/', 'slice' => 4],
            ['screen' => 'packaging-configs-list', 'label' => 'Packaging configurations', 'url' => '/packaging-configs/', 'slice' => 4],
            ['screen' => 'standard-costs-list', 'label' => 'Standard costs', 'url' => '/standard-costs/', 'slice' => 4],
            ['screen' => 'approvals-list', 'label' => 'Approvals', 'url' => '/approvals/', 'slice' => 4],
        ]],
        ['label' => 'Production', 'icon' => 'feather-droplet', 'items' => [
            ['screen' => 'production-orders-list', 'label' => 'Production orders', 'url' => '/production-orders/', 'slice' => 5],
            ['screen' => 'production-calendar', 'label' => 'Vessel calendar', 'url' => '/production-orders/calendar', 'slice' => 5],
            ['screen' => 'press-runs-list', 'label' => 'Press runs', 'url' => '/press-runs/', 'slice' => 6],
            ['screen' => 'tank-board', 'label' => 'Tank board', 'url' => '/tank-board/', 'slice' => 6],
            ['screen' => 'batches-list', 'label' => 'Batches', 'url' => '/batches/', 'slice' => 6],
        ]],
        ['label' => 'Packaging', 'icon' => 'feather-box', 'items' => [
            ['screen' => 'packaging-runs-list', 'label' => 'Packaging runs', 'url' => '/packaging-runs/', 'slice' => 7],
            ['screen' => 'finished-lots-list', 'label' => 'Finished goods', 'url' => '/finished-lots/', 'slice' => 7],
            ['screen' => 'kegs-list', 'label' => 'Kegs', 'url' => '/kegs/', 'slice' => 7],
        ]],
        ['label' => 'Quality', 'icon' => 'feather-check-circle', 'items' => [
            ['screen' => 'lab-list', 'label' => 'Lab', 'url' => '/lab/', 'slice' => 8],
            ['screen' => 'sensory-list', 'label' => 'Sensory', 'url' => '/sensory/', 'slice' => 8],
            ['screen' => 'release-queue', 'label' => 'Release queue', 'url' => '/releases/', 'slice' => 8],
        ]],
        ['label' => 'Reports', 'icon' => 'feather-bar-chart-2', 'items' => [
            ['screen' => 'report-yields', 'label' => 'Yields', 'url' => '/reports/yields', 'slice' => 9],
            ['screen' => 'report-juice-yield', 'label' => 'Juice yield', 'url' => '/reports/juice-yield', 'slice' => 9],
            ['screen' => 'report-batch-costs', 'label' => 'Batch costs', 'url' => '/reports/batch-costs', 'slice' => 9],
            ['screen' => 'report-valuation', 'label' => 'Valuation', 'url' => '/reports/valuation', 'slice' => 9],
        ]],
        ['label' => 'Sales', 'icon' => 'feather-shopping-cart', 'items' => [
            ['screen' => 'orders-list', 'label' => 'Customer orders', 'url' => '/orders/', 'slice' => 12],
        ]],
        ['label' => 'Compliance', 'icon' => 'feather-shield', 'items' => [
            ['screen' => 'customers-list', 'label' => 'Customers', 'url' => '/customers/', 'slice' => 10],
            ['screen' => 'removals-list', 'label' => 'Removals', 'url' => '/removals/', 'slice' => 10],
            ['screen' => 'ttb-reports-list', 'label' => 'TTB reports', 'url' => '/ttb-reports/', 'slice' => 10],
            ['screen' => 'trace', 'label' => 'Trace', 'url' => '/trace/', 'slice' => 10],
        ]],
        ['label' => 'Setup', 'icon' => 'feather-settings', 'items' => [
            ['screen' => 'premises-list', 'label' => 'Premises', 'url' => '/premises/', 'slice' => 1],
            ['screen' => 'locations-list', 'label' => 'Locations', 'url' => '/locations/', 'slice' => 1],
            ['screen' => 'vessels-list', 'label' => 'Vessels', 'url' => '/vessels/', 'slice' => 1],
            ['screen' => 'items-list', 'label' => 'Items', 'url' => '/items/', 'slice' => 1],
            ['screen' => 'units-list', 'label' => 'Units', 'url' => '/units/', 'slice' => 1],
            ['screen' => 'suppliers-list', 'label' => 'Suppliers', 'url' => '/suppliers/', 'slice' => 1],
            ['screen' => 'reason-codes-list', 'label' => 'Reason codes', 'url' => '/reason-codes/', 'slice' => 1],
            ['screen' => 'users-list', 'label' => 'Users', 'url' => '/users/', 'slice' => 1, 'roles' => ['owner']],
            ['screen' => 'settings-client', 'label' => 'Organization', 'url' => '/settings/client', 'slice' => 1, 'roles' => ['owner']],
            ['screen' => 'settings-mcp-tokens', 'label' => 'AI access tokens', 'url' => '/settings/mcp-tokens', 'slice' => 11, 'roles' => ['owner']],
        ]],
    ];
}

/** Look up a navigation item by its canonical URL (used by the stub router). */
function navigation_item_for_path(string $path): ?array
{
    $path = rtrim($path, '/') . '/';
    foreach (navigation_groups() as $group) {
        foreach ($group['items'] as $item) {
            if (rtrim($item['url'], '/') . '/' === $path) {
                return $item + ['group' => $group['label']];
            }
        }
    }
    return null;
}
