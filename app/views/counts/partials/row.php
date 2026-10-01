<?php /** @var array $count */ $id = (int) $count['id']; ?>
<tr id="count-row-<?= e($id) ?>">
    <td id="count-row-<?= e($id) ?>-number"><a <?= nav_attrs('/counts/' . $id) ?>><?= status_dot(status_color($count['status'])) ?><span><?= e($count['number']) ?></span></a></td>
    <td id="count-row-<?= e($id) ?>-location"><?= e($count['location_name']) ?></td>
    <td id="count-row-<?= e($id) ?>-kind"><?= badge(COUNT_KINDS[$count['kind']] ?? humanize($count['kind']), 'info') ?></td>
    <td id="count-row-<?= e($id) ?>-lines"><?= e($count['line_count']) ?></td>
    <td id="count-row-<?= e($id) ?>-variance-lines" class="<?= (int) $count['variance_count'] > 0 ? 'text-danger' : '' ?>"><?= e($count['variance_count']) ?></td>
    <td id="count-row-<?= e($id) ?>-started"><?= e(format_date($count['started_at'])) ?></td>
    <td id="count-row-<?= e($id) ?>-status"><?= status_badge($count['status']) ?></td>
    <td id="count-row-<?= e($id) ?>-approved-by"><?= e($count['approved_by_name'] ?? '') ?></td>
</tr>
