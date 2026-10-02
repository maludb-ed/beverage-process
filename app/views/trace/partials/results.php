<?php /** @var array $trace */
$rows = $trace['rows'];
$exportUrl = '/trace/' . query_string(['lot_number' => $trace['lot_number'], 'batch_number' => $trace['batch_number'], 'format' => 'csv']);
?>
<div class="col-lg-12" id="trace-results">
    <?php if ($trace['error'] !== null): ?>
        <div class="alert alert-warning" id="trace-error"><?= e($trace['error']) ?></div>
    <?php endif; ?>
    <?php if ($trace['direction'] === null): ?>
        <div class="card" id="trace-prompt">
            <div class="card-body text-center text-muted py-5">
                <i class="feather-git-branch fs-1 d-block mb-3"></i>
                Enter a lot number to trace it forward to batches, finished lots and customers, or a batch number (or a finished lot) to trace it back to its ingredients and fruit.
            </div>
        </div>
    <?php else: ?>
        <?= view('trace/partials/summary.php', ['summary' => $trace['summary']]) ?>
        <div class="card" id="trace-results-card">
            <div class="card-header">
                <h5 class="card-title"><?= e(ucfirst($trace['direction'])) ?> from <?= e($trace['start']) ?></h5>
                <a class="btn btn-sm btn-light-brand" id="trace-export-btn" href="<?= e($exportUrl) ?>" download><i class="feather-download me-1"></i>Export CSV</a>
            </div>
            <div class="card-body custom-card-action p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="trace-results-table">
                        <thead class="thead-light"><tr><th>Level</th><th>Kind</th><th>Id</th><th>Detail</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $i => $row): $url = (TRACE_KIND_URLS[$row['kind']] ?? null); ?>
                            <tr id="trace-row-<?= e($i) ?>">
                                <td id="trace-row-<?= e($i) ?>-level"><span class="ps-<?= e(min(5, max(0, (int) $row['level']))) ?> d-inline-block"><?= e($row['level']) ?></span></td>
                                <td id="trace-row-<?= e($i) ?>-kind"><?= badge(TRACE_KIND_LABELS[$row['kind']] ?? humanize($row['kind']), TRACE_KIND_COLORS[$row['kind']] ?? 'secondary') ?></td>
                                <td id="trace-row-<?= e($i) ?>-label"><span class="ps-<?= e(min(5, max(0, (int) $row['level']))) ?> d-inline-block"><?php if ($url): ?><a <?= nav_attrs($url . (int) $row['id']) ?>><?= e($row['label']) ?></a><?php else: ?><?= e($row['label']) ?><?php endif; ?></span></td>
                                <td id="trace-row-<?= e($i) ?>-detail" class="fs-12"><?= e(trace_detail_text($row)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr id="trace-results-empty"><td colspan="4" class="text-center text-muted py-5"><i class="feather-inbox fs-1 d-block mb-3"></i><?= e($trace['direction'] === 'forward' ? 'Nothing downstream yet: no batch has used this lot.' : 'Nothing upstream recorded for this batch.') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer fs-12 text-muted" id="trace-results-count"><?= e(count($rows)) ?> rows · every row shown (no paging for recalls)</div>
        </div>
    <?php endif; ?>
</div>
