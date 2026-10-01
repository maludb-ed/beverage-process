<?php /** @var array $product  @var bool $canEdit */ $id = (int) $product['id']; $viewUrl = '/products/' . $id; $r = 'product-row-' . $id; ?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-name"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($product['status'])) ?><span><?= e($product['name']) ?></span> <small class="text-muted"><?= e($product['code']) ?></small></a></td>
    <td id="<?= e($r) ?>-style"><?= e($product['style']) ?></td>
    <td id="<?= e($r) ?>-beverage"><?= e(PRODUCT_BEVERAGES[$product['beverage_type']] ?? $product['beverage_type']) ?></td>
    <td id="<?= e($r) ?>-tax-class"><?= e(PRODUCT_TAX_CLASSES[$product['intended_tax_class']] ?? $product['intended_tax_class']) ?></td>
    <td id="<?= e($r) ?>-abv"><?= $product['target_abv'] === null ? '' : e(number_format((float) $product['target_abv'], 2)) . '%' ?></td>
    <td id="<?= e($r) ?>-fruit-share"><?= $product['target_fruit_share_pct'] === null ? '' : e(rtrim(rtrim(number_format((float) $product['target_fruit_share_pct'], 2), '0'), '.')) . '%' ?></td>
    <td id="<?= e($r) ?>-recipe"><?= $product['active_version_no'] === null ? '<span class="text-muted">none</span>' : 'v' . e($product['active_version_no']) ?></td>
    <td id="<?= e($r) ?>-status"><?= status_badge($product['status']) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button($r . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
