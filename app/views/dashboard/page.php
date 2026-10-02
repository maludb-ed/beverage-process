<?php /** @var array $stats  @var array $recent */ ?>
<?= view('shared/page-header.php', ['title' => 'Dashboard', 'screen' => 'dashboard', 'crumbs' => ['Dashboard' => null]]) ?>
<div class="main-content" id="dashboard-content">
    <div class="row">
        <?php
        $cards = [
            ['id' => 'vessels-in-use', 'icon' => 'feather-droplet', 'label' => 'Vessels in use', 'value' => $stats['vessels_in_use'], 'total' => $stats['vessels_total'], 'sub' => 'of all vessels', 'color' => 'primary'],
            ['id' => 'batches-active', 'icon' => 'feather-activity', 'label' => 'Active batches', 'value' => $stats['batches_active'], 'total' => null, 'sub' => 'in fermentation or maturation', 'color' => 'info'],
            ['id' => 'lots-quarantine', 'icon' => 'feather-alert-circle', 'label' => 'Lots in quarantine', 'value' => $stats['lots_quarantine'], 'total' => null, 'sub' => 'awaiting release', 'color' => 'warning'],
            ['id' => 'orders-open', 'icon' => 'feather-truck', 'label' => 'Open purchase orders', 'value' => $stats['po_open'], 'total' => null, 'sub' => $stats['po_overdue'] . ' overdue', 'color' => 'success'],
        ];
        foreach ($cards as $card):
            $pct = $card['total'] ? (int) round(100 * $card['value'] / max(1, $card['total'])) : null;
        ?>
        <div class="col-xxl-3 col-md-6" id="dashboard-stat-<?= e($card['id']) ?>">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                        <div class="d-flex gap-4 align-items-center">
                            <div class="avatar-text avatar-lg bg-gray-200"><i class="<?= e($card['icon']) ?>"></i></div>
                            <div>
                                <div class="fs-4 fw-bold text-dark"><span class="counter"><?= e($card['value']) ?></span></div>
                                <h3 class="fs-13 fw-semibold text-truncate-1-line"><?= e($card['label']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fs-12 fw-medium text-muted text-truncate-1-line"><?= e($card['sub']) ?></span>
                            <?php if ($pct !== null): ?><div class="w-100 text-end"><span class="fs-12 text-dark"><?= e($card['value']) ?> / <?= e($card['total']) ?></span> <span class="fs-11 text-muted">(<?= e($pct) ?>%)</span></div><?php endif; ?>
                        </div>
                        <?php if ($pct !== null): ?><div class="progress mt-2 ht-3"><div class="progress-bar bg-<?= e($card['color']) ?>" role="progressbar" style="width: <?= e($pct) ?>%"></div></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-lg-12" id="dashboard-recent">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recent activity</h5>
                    <div class="card-header-action">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25"><div data-bs-toggle="tooltip" title="Options"><i class="feather-more-vertical"></i></div></a>
                            <div class="dropdown-menu dropdown-menu-end" id="dashboard-recent-menu">
                                <a href="/activity/" class="dropdown-item" hx-get="/activity/" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/activity/"><i class="feather-list"></i>View all</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body custom-card-action p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="dashboard-recent-table">
                            <thead class="thead-light"><tr>
                                <th id="dashboard-recent-col-when">When</th>
                                <th id="dashboard-recent-col-who">Who</th>
                                <th id="dashboard-recent-col-action">Action</th>
                                <th id="dashboard-recent-col-record">Record</th>
                            </tr></thead>
                            <tbody id="dashboard-recent-tbody">
                                <?php if ($recent === []): ?>
                                    <tr id="dashboard-recent-empty"><td colspan="4" class="text-center text-muted py-4">No activity yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recent as $row): ?>
                                    <tr id="activity-row-<?= e($row['id']) ?>">
                                        <td id="activity-row-<?= e($row['id']) ?>-when"><?= e(format_datetime($row['occurred_at'])) ?></td>
                                        <td id="activity-row-<?= e($row['id']) ?>-who"><?= e($row['actor_name'] ?? $row['actor_label']) ?></td>
                                        <td id="activity-row-<?= e($row['id']) ?>-action"><span class="badge bg-soft-info text-info"><?= e(str_replace('_', ' ', $row['action'])) ?></span> <small class="text-muted"><?= e($row['screen'] ?? '') ?></small></td>
                                        <td id="activity-row-<?= e($row['id']) ?>-record"><?= e(trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_label'] ?? ''))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
