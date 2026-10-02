<?php /** @var array $rows  @var int $page  @var bool $hasMore  @var string $q */ $qs = $q !== '' ? '&q=' . rawurlencode($q) : ''; ?>
<div class="col-lg-12" id="activity-list-results">
    <div class="card" id="activity-list-card">
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="activity-list-table">
                    <thead class="thead-light"><tr>
                        <th id="activity-list-col-when">When</th>
                        <th id="activity-list-col-who">Who</th>
                        <th id="activity-list-col-source">Source</th>
                        <th id="activity-list-col-action">Action</th>
                        <th id="activity-list-col-screen">Screen</th>
                        <th id="activity-list-col-record">Record</th>
                    </tr></thead>
                    <tbody id="activity-list-tbody">
                        <?php if ($rows === []): ?><tr id="activity-list-empty"><td colspan="6" class="text-center text-muted py-4">No activity matches.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr id="activity-row-<?= e($row['id']) ?>">
                                <td id="activity-row-<?= e($row['id']) ?>-when"><?= e(format_datetime($row['occurred_at'])) ?></td>
                                <td id="activity-row-<?= e($row['id']) ?>-who"><?= e($row['actor_name'] ?? $row['actor_label']) ?></td>
                                <td id="activity-row-<?= e($row['id']) ?>-source"><span class="badge bg-soft-secondary text-secondary"><?= e(str_replace('_', ' ', $row['source'])) ?></span></td>
                                <td id="activity-row-<?= e($row['id']) ?>-action"><span class="badge bg-soft-info text-info"><?= e(str_replace('_', ' ', $row['action'])) ?></span></td>
                                <td id="activity-row-<?= e($row['id']) ?>-screen"><small class="text-muted"><?= e($row['screen'] ?? '') ?></small></td>
                                <td id="activity-row-<?= e($row['id']) ?>-record"><?= e(trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_label'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" id="activity-list-footer">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fs-12 text-muted" id="activity-list-count">Page <?= e($page) ?></div>
                <nav aria-label="Activity pages"><ul class="pagination pagination-sm mb-0" id="activity-list-pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="javascript:void(0);" hx-get="/activity/?page=<?= e($page - 1) . $qs ?>" hx-target="#activity-list-results" hx-swap="outerHTML" aria-label="Previous"><i class="feather-chevron-left"></i></a></li>
                    <li class="page-item active"><a class="page-link" href="javascript:void(0);"><?= e($page) ?></a></li>
                    <li class="page-item <?= $hasMore ? '' : 'disabled' ?>"><a class="page-link" href="javascript:void(0);" hx-get="/activity/?page=<?= e($page + 1) . $qs ?>" hx-target="#activity-list-results" hx-swap="outerHTML" aria-label="Next"><i class="feather-chevron-right"></i></a></li>
                </ul></nav>
            </div>
        </div>
    </div>
</div>
