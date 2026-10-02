<?php
/**
 * Canonical "Traffic Reports" list card with server-rendered sort and pagination.
 * @var string $screen   screen id, e.g. "premises-list"; the region id is {screen}-results
 * @var string $title    card title
 * @var array  $columns  [['key' => 'name', 'label' => 'Name', 'sort' => 'name'|null, 'class' => 'text-end'|null], ...]
 * @var string $rowsHtml rendered <tr> rows
 * @var array  $paging   from paged_query(): total, page, pages, page_size
 * @var string $url      list URL
 * @var array  $query    current filters and sort (without page)
 * @var string $emptyMessage
 */
$query = $query ?? [];
$currentSort = (string) ($query['sort'] ?? '');
$link = static function (array $overrides) use ($url, $query): string {
    return $url . query_string(array_merge($query, $overrides));
};
$target = '#' . $screen . '-results';
$from = $paging['total'] === 0 ? 0 : ($paging['page'] - 1) * $paging['page_size'] + 1;
$to = min($paging['total'], $paging['page'] * $paging['page_size']);
?>
<div class="col-lg-12" id="<?= e($screen) ?>-results">
    <div class="card" id="<?= e($screen) ?>-card">
        <div class="card-header">
            <h5 class="card-title"><?= e($title) ?></h5>
            <div class="card-header-action">
                <div class="card-header-btn">
                    <div data-bs-toggle="tooltip" title="Refresh">
                        <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" id="<?= e($screen) ?>-refresh-btn" hx-get="<?= e($link(['page' => $paging['page']])) ?>" hx-target="<?= e($target) ?>" hx-swap="outerHTML"> </a>
                    </div>
                    <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                        <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="<?= e($screen) ?>-table">
                    <thead class="thead-light">
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th id="<?= e($screen) ?>-col-<?= e($column['key']) ?>" class="<?= e($column['class'] ?? '') ?>">
                                    <?php if (!empty($column['sort'])):
                                        $isAsc = $currentSort === $column['sort'];
                                        $isDesc = $currentSort === '-' . $column['sort'];
                                        $next = $isAsc ? '-' . $column['sort'] : $column['sort']; ?>
                                        <a href="javascript:void(0);" class="text-reset" hx-get="<?= e($link(['sort' => $next, 'page' => null])) ?>" hx-target="<?= e($target) ?>" hx-swap="outerHTML"><?= e($column['label']) ?><?php if ($isAsc): ?> <i class="feather-chevron-up"></i><?php elseif ($isDesc): ?> <i class="feather-chevron-down"></i><?php endif; ?></a>
                                    <?php else: ?>
                                        <?= e($column['label']) ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="<?= e($screen) ?>-tbody">
                        <?php if (trim($rowsHtml) === ''): ?>
                            <tr id="<?= e($screen) ?>-empty"><td colspan="<?= e(count($columns)) ?>" class="text-center text-muted py-5"><i class="feather-inbox fs-1 d-block mb-3"></i><?= e($emptyMessage ?? 'Nothing here yet.') ?></td></tr>
                        <?php else: ?>
                            <?= $rowsHtml ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" id="<?= e($screen) ?>-footer">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fs-12 text-muted" id="<?= e($screen) ?>-count">Showing <?= e(number_format($from)) ?> to <?= e(number_format($to)) ?> of <?= e(number_format($paging['total'])) ?></div>
                <?php if ($paging['pages'] > 1): ?>
                <nav aria-label="<?= e($title) ?> pages">
                    <ul class="pagination pagination-sm mb-0" id="<?= e($screen) ?>-pagination">
                        <li class="page-item <?= $paging['page'] <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="javascript:void(0);" aria-label="Previous" hx-get="<?= e($link(['page' => $paging['page'] - 1])) ?>" hx-target="<?= e($target) ?>" hx-swap="outerHTML"><i class="feather-chevron-left"></i></a>
                        </li>
                        <?php for ($p = max(1, $paging['page'] - 2); $p <= min($paging['pages'], $paging['page'] + 2); $p++): ?>
                            <li class="page-item <?= $p === $paging['page'] ? 'active' : '' ?>"<?= $p === $paging['page'] ? ' aria-current="page"' : '' ?>>
                                <a class="page-link" href="javascript:void(0);" hx-get="<?= e($link(['page' => $p])) ?>" hx-target="<?= e($target) ?>" hx-swap="outerHTML"><?= e($p) ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $paging['page'] >= $paging['pages'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="javascript:void(0);" aria-label="Next" hx-get="<?= e($link(['page' => $paging['page'] + 1])) ?>" hx-target="<?= e($target) ?>" hx-swap="outerHTML"><i class="feather-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
