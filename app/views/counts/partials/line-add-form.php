<?php /** @var array $count  @var array $itemOptions  @var array $lots  @var array $input  @var array $errors */
$cid = (int) $count['id'];
$errors = $errors ?? [];
?>
<div class="p-3 border-bottom" id="count-view-add-line-form">
    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'count-view-add-line-errors']) ?>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="fw-semibold fs-12" for="count-view-add-line-item">Item</label>
            <select class="form-select" id="count-view-add-line-item" name="item_id" hx-get="/counts/<?= e($cid) ?>/lines/lots" hx-trigger="change" hx-target="#count-view-add-line-lot-wrap" hx-swap="innerHTML">
                <option value="">Choose an item</option>
                <?php foreach ($itemOptions as $itemId => $label): ?><option value="<?= e($itemId) ?>"<?= (int) ($input['item_id'] ?? 0) === (int) $itemId ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5" id="count-view-add-line-lot-wrap">
            <?= view('counts/partials/line-add-lots.php', ['lots' => $lots, 'selected' => $input['lot_id'] ?? '']) ?>
        </div>
        <div class="col-12 col-md-2">
            <button type="button" class="btn btn-primary w-100" id="count-view-add-line-save-btn" hx-post="/counts/<?= e($cid) ?>/lines/add" hx-include="#count-view-add-line-form" hx-target="#count-view-sheet" hx-swap="outerHTML"><i class="feather-plus me-1"></i>Add</button>
        </div>
    </div>
</div>
