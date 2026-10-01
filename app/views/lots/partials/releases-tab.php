<?php /** @var array $lot  @var array $decisions  @var bool $canRelease */ $id = (int) $lot['id']; ?>
<div class="p-4">
    <?php if ($canRelease): ?><div class="mb-4 text-end"><?= nav_button('lot-view-release-btn', '/lots/' . $id . '/release', 'Release decision', 'feather-check-circle', 'btn btn-sm btn-primary') ?></div><?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="lot-view-releases-table">
            <thead class="thead-light"><tr><th>When</th><th>Change</th><th>Basis</th><th>Who</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($decisions as $decision): $did = (int) $decision['id']; ?>
                <tr id="lot-release-row-<?= e($did) ?>">
                    <td><?= e(format_datetime($decision['decided_at'])) ?></td>
                    <td><?= status_badge($decision['from_status']) ?> <i class="feather-arrow-right fs-11"></i> <?= status_badge($decision['to_status']) ?></td>
                    <td><?= e(RELEASE_BASES[$decision['basis']] ?? $decision['basis']) ?><?= $decision['is_override'] ? ' ' . badge('Override', 'danger') . ' <small class="text-muted">' . e($decision['reason_name'] ?? '') . '</small>' : '' ?></td>
                    <td><?= e($decision['decided_by_name']) ?></td>
                    <td><small class="text-muted"><?= e($decision['note'] ?? '') ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($decisions === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No release decisions yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
