<?php /** @var array $rows  @var array $query  @var bool $canEdit */
$refresh = '/equipment/' . query_string(['q' => $query['q'] ?: null, 'kind' => $query['kind'] ?: null, 'status' => $query['status'] ?: null, 'premises_id' => $query['premises_id'] ?? null]);
$lastPremises = null;
$manyPremises = count(array_unique(array_column($rows, 'premises_id'))) > 1;
?>
<div id="equipment-list-results" hx-get="<?= e($refresh) ?>" hx-trigger="equipmentChanged from:body, reservationsChanged from:body" hx-target="#equipment-list-results" hx-swap="outerHTML">
<div class="row g-3" id="equipment-list-grid">
    <?php foreach ($rows as $row): $id = (int) $row['id']; $c = 'equipment-card-' . $id;
        $premisesChanged = $manyPremises && $lastPremises !== (int) $row['premises_id']; $lastPremises = (int) $row['premises_id'];
        $color = status_color($row['status']); ?>
    <?php if ($premisesChanged): ?>
    <div class="col-12" id="equipment-list-premises-<?= e($lastPremises) ?>"><h6 class="fw-bold mb-0 mt-2 text-uppercase fs-12 text-muted"><?= e($row['premises_name']) ?></h6></div>
    <?php endif; ?>
    <div class="col-12 col-md-6 col-xl-4" id="<?= e($c) ?>">
        <div class="card mb-0<?= $row['active'] ? '' : ' opacity-75' ?>">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-<?= e($color) ?> text-<?= e($color) ?>"><i class="feather-tool"></i></div>
                        <div>
                            <a class="fw-bold text-dark" id="<?= e($c) ?>-name" <?= nav_attrs('/equipment/' . $id) ?>><?= e($row['name']) ?></a>
                            <div class="fs-12 text-muted" id="<?= e($c) ?>-kind"><?= e(EQUIPMENT_KINDS[$row['kind']] ?? humanize($row['kind'])) ?><?= $row['rating'] ? ' · ' . e($row['rating']) : '' ?></div>
                        </div>
                    </div>
                    <div class="hstack gap-2">
                        <?= status_badge($row['status'], $c . '-status') ?>
                        <?php if (!$row['active']): ?><?= badge('Inactive', 'secondary', $c . '-inactive') ?><?php endif; ?>
                        <?php if ($canEdit): ?><?= row_edit_button($c . '-edit-btn', '/equipment/' . $id . '/edit') ?><?php endif; ?>
                    </div>
                </div>
                <div class="fs-12 text-muted mb-2" id="<?= e($c) ?>-where"><i class="feather-map-pin me-1"></i><?= e($row['location_name'] ?? $row['premises_name']) ?></div>
                <?php if ($row['next_reservation_id'] === null): ?>
                    <div class="fs-12 text-muted" id="<?= e($c) ?>-next">Nothing booked ahead</div>
                <?php else: ?>
                    <div class="fs-12" id="<?= e($c) ?>-next">
                        <span class="text-muted">Next:</span>
                        <?php if ($row['next_number'] !== null): ?>
                            <span class="fw-semibold"><?= e($row['next_number']) ?></span> <span class="text-muted"><?= e($row['next_label']) ?></span>
                        <?php else: ?>
                            <span class="fw-semibold"><?= e(humanize($row['next_kind'])) ?></span>
                        <?php endif; ?>
                        <span class="text-muted">· <?= e(format_date($row['next_from'])) ?><?= $row['next_to'] !== $row['next_from'] ? ' – ' . e(format_date($row['next_to'])) : '' ?></span>
                        <?php if ((int) $row['bookings_ahead'] > 1): ?><span class="text-muted">· <?= e((int) $row['bookings_ahead']) ?> ahead</span><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
    <div class="col-12" id="equipment-list-empty"><div class="card"><div class="card-body text-center py-5 text-muted"><i class="feather-inbox fs-1 d-block mb-3"></i><?= ($query['q'] ?? '') !== '' || $query['kind'] !== '' || $query['status'] !== '' ? 'No equipment matches.' : 'No equipment yet. Add the mill, the pumps, the filter and the lines; tanks and presses are vessels.' ?></div></div></div>
    <?php endif; ?>
</div>
</div>
