<?php /** @var array $report */ $id = (int) $report['id']; $viewUrl = '/ttb-reports/' . $id; ?>
<tr id="ttb-report-row-<?= e($id) ?>">
    <td id="ttb-report-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($report['status'])) ?><span><?= e($report['number']) ?></span></a></td>
    <td id="ttb-report-row-<?= e($id) ?>-premises"><?= e($report['premises_name']) ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-form"><?= e($report['form_code']) ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-period"><?= e(format_date($report['period_start'])) ?> to <?= e(format_date($report['period_end'])) ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-status"><?= status_badge($report['status']) ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-generated"><?= e(format_datetime($report['generated_at'])) ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-filed"><?= e(format_date($report['filed_at']) ?: '—') ?></td>
    <td id="ttb-report-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <a id="ttb-report-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a>
        </div>
    </td>
</tr>
