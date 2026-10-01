<?php /** @var array $count  @var array $lines  @var bool $canEdit  @var ?string $addForm */
$cid = (int) $count['id'];
?>
<div id="count-view-sheet">
    <?php if ($canEdit): ?>
        <div class="p-3 border-bottom text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="count-view-add-line-btn" hx-get="/counts/<?= e($cid) ?>/lines/new" hx-target="#count-view-add-line-slot" hx-swap="innerHTML"><i class="feather-plus me-1"></i>Add line</button>
        </div>
    <?php endif; ?>
    <div id="count-view-add-line-slot"><?= $addForm ?? '' ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="count-sheet-table">
            <thead class="thead-light"><tr><th>Item</th><th>Lot</th><th>Expected</th><th>Counted</th><th>Variance</th><th>Counted by</th><th>Note</th></tr></thead>
            <tbody id="count-sheet-tbody">
            <?php foreach ($lines as $line): ?>
                <?= view('counts/partials/sheet-row.php', ['line' => $line, 'canEdit' => $canEdit, 'error' => '']) ?>
            <?php endforeach; ?>
            <?php if ($lines === []): ?><tr id="count-sheet-empty"><td colspan="7" class="text-center text-muted py-4">Nothing was expected at this location. Add the lots you find.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
