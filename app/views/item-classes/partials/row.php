<?php /** @var array $class  @var bool $canEdit */ $id = (int) $class['id']; $editUrl = '/item-classes/' . $id . '/edit'; $r = 'item-class-row-' . $id; ?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-order"><?= e($class['display_order']) ?></td>
    <td id="<?= e($r) ?>-code"><a <?= $canEdit ? nav_attrs($editUrl) : 'href="javascript:void(0);"' ?>><?= status_dot($class['active'] ? 'success' : 'secondary') ?><span><?= e($class['code']) ?></span></a></td>
    <td id="<?= e($r) ?>-name"><?= e($class['name']) ?></td>
    <td id="<?= e($r) ?>-kind"><?= badge(ITEM_CLASS_KINDS[$class['kind']] ?? $class['kind'], $class['kind'] === 'finished' ? 'primary' : 'info') ?></td>
    <td id="<?= e($r) ?>-purchasable"><?= e(yes_no($class['purchasable'])) ?></td>
    <td id="<?= e($r) ?>-recipe-ingredient"><?= e(yes_no($class['recipe_ingredient'])) ?></td>
    <td id="<?= e($r) ?>-items"><a <?= nav_attrs('/items/?item_class=' . rawurlencode($class['code'])) ?>><?= e($class['item_count']) ?></a></td>
    <td id="<?= e($r) ?>-origin"><?= $class['is_builtin'] ? badge('Built in', 'secondary') : badge('Custom', 'success') ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button($r . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
