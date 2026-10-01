<?php /** @var array $keg  @var array $movements  @var array $lots  @var bool $canEdit  @var array $errors  @var ?string $notice  @var array $input */
$errors = $errors ?? [];
$input = $input ?? [];
$id = (int) $keg['id'];
$state = $keg['state'];
$can = static fn(string $event) => in_array($state, KEG_TRANSITIONS[$event], true);
$button = static fn(string $event, string $label, string $icon, string $class, ?string $confirm = null) =>
    '<button type="button" class="btn ' . e($class) . '" id="keg-view-' . e(str_replace('_', '-', $event)) . '-btn" hx-post="/kegs/' . e($id) . '/state" hx-vals=\'{"event":"' . e($event) . '"}\' hx-target="#keg-view-state-card" hx-swap="outerHTML"'
    . ($confirm !== null ? ' hx-confirm="' . e($confirm) . '"' : '') . '><i class="' . e($icon) . ' me-2"></i><span>' . e($label) . '</span></button>';
?>
<div class="card stretch stretch-full" id="keg-view-state-card">
    <div class="card-header"><h5 class="card-title">State</h5><div><?= status_badge($state, 'keg-view-state') ?></div></div>
    <div class="card-body">
        <?php if (!empty($notice)): ?><div class="alert alert-success" id="keg-view-notice"><?= e($notice) ?></div><?php endif; ?>
        <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'keg-view-errors']) ?>
        <ul class="list-unstyled mb-0">
            <li class="hstack justify-content-between mb-3"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Holder</span><span id="keg-view-holder"><?= e($keg['holder_name'] ?? ($keg['current_holder_kind'] === 'unknown' ? 'Unknown' : '—')) ?></span></li>
            <li class="hstack justify-content-between mb-3"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Contents</span><span id="keg-view-contents"><?php if ($keg['current_lot_id']): ?><a <?= nav_attrs('/finished-lots/' . (int) $keg['current_lot_id']) ?>><?= e($keg['lot_number']) ?></a><?php else: ?>—<?php endif; ?></span></li>
            <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Last moved</span><span id="keg-view-last-moved"><?= e($keg['last_moved_at'] ? format_datetime($keg['last_moved_at']) . ' (' . $keg['days_since_moved'] . ' days)' : '—') ?></span></li>
        </ul>
    </div>
    <?php if ($canEdit): ?>
    <div class="p-4 border-top">
        <?php if ($can('fill')): ?>
        <form class="mb-3" id="keg-view-fill-form" method="post" action="/kegs/<?= e($id) ?>/state" hx-post="/kegs/<?= e($id) ?>/state" hx-target="#keg-view-state-card" hx-swap="outerHTML">
            <?= csrf_field() ?>
            <input type="hidden" name="event" value="fill" />
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="fw-semibold fs-12" for="keg-view-field-finished-lot">Fill from finished lot</label>
                    <select class="form-select<?= isset($errors['finished_lot']) ? ' is-invalid' : '' ?>" id="keg-view-field-finished-lot" name="finished_lot">
                        <option value=""><?= $lots === [] ? 'No keg lot has units available' : 'Choose a lot' ?></option>
                        <?php foreach ($lots as $lotId => $label): ?><option value="<?= e($lotId) ?>"<?= (int) ($input['finished_lot'] ?? 0) === (int) $lotId ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4"><button type="submit" class="btn btn-primary w-100" id="keg-view-fill-btn"<?= $lots === [] ? ' disabled' : '' ?>><i class="feather-droplet me-2"></i>Fill</button></div>
            </div>
        </form>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2" id="keg-view-actions">
            <?php if ($can('return')): ?><?= nav_button('keg-view-return-btn', '/kegs/return' . query_string(['serials' => $keg['serial']]), 'Return', 'feather-corner-down-left', 'btn btn-light-brand') ?><?php endif; ?>
            <?php if ($can('clean')): ?><?= $button('clean', 'Clean', 'feather-droplet', 'btn-primary') ?><?php endif; ?>
            <?php if ($can('found')): ?><?= $button('found', 'Found', 'feather-search', 'btn-primary') ?><?php endif; ?>
            <?php if ($can('mark_lost')): ?><?= $button('mark_lost', 'Mark lost', 'feather-alert-triangle', 'btn-light-brand', 'Mark keg ' . $keg['serial'] . ' as lost?') ?><?php endif; ?>
            <?php if ($can('retire')): ?><?= $button('retire', 'Retire', 'feather-archive', 'btn-light-brand', 'Retire keg ' . $keg['serial'] . '? It leaves the fleet for good.') ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="border-top">
        <div class="px-4 pt-4 fw-semibold">Movement history</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="keg-view-movements-table">
                <thead class="thead-light"><tr><th>When</th><th>Event</th><th>Lot</th><th>Customer</th><th>Location</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $movement): $mid = (int) $movement['id']; ?>
                    <tr id="keg-movement-row-<?= e($mid) ?>">
                        <td id="keg-movement-row-<?= e($mid) ?>-when"><?= e(format_datetime($movement['occurred_at'])) ?></td>
                        <td id="keg-movement-row-<?= e($mid) ?>-event"><?= badge(KEG_EVENT_LABELS[$movement['event']] ?? humanize($movement['event']), 'secondary') ?></td>
                        <td id="keg-movement-row-<?= e($mid) ?>-lot"><?= e($movement['lot_number'] ?? '—') ?></td>
                        <td id="keg-movement-row-<?= e($mid) ?>-customer"><?= e($movement['customer_name'] ?? '—') ?></td>
                        <td id="keg-movement-row-<?= e($mid) ?>-location"><?= e($movement['location_name'] ?? '—') ?></td>
                        <td id="keg-movement-row-<?= e($mid) ?>-by"><?= e($movement['actor_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($movements === []): ?><tr><td colspan="6" class="text-center text-muted py-4" id="keg-view-movements-empty">No movements yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
