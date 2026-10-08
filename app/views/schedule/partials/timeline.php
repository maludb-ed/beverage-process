<?php /** @var array $resources  @var array $bookings  @var array $occupants  @var array $query  @var DateTimeImmutable $gridStart  @var DateTimeImmutable $gridEnd  @var string $today  @var bool $canEdit */
$tz = new DateTimeZone((string) config('app.timezone'));
$days = [];
for ($d = $gridStart; $d < $gridEnd; $d = $d->modify('+1 day')) { $days[] = $d; }
$dayCount = count($days);
$windowStart = $gridStart->format('Y-m-d');
$windowLast = $gridEnd->modify('-1 day')->format('Y-m-d');
$link = static fn(array $overrides) => '/schedule/' . query_string(array_merge($query, $overrides));
$weeks = (int) $query['weeks'];
$prev = $link(['from' => $gridStart->modify('-' . $weeks . ' weeks')->format('Y-m-d')]);
$next = $link(['from' => $gridEnd->format('Y-m-d')]);
$thisWeek = $link(['from' => (new DateTimeImmutable($today, $tz))->modify('monday this week')->format('Y-m-d')]);
$navBtn = static fn(string $id, string $target, string $icon, string $label) => '<a class="btn btn-sm btn-light-brand" id="' . e($id) . '" href="' . e($target) . '" hx-get="' . e($target) . '" hx-target="#schedule-results" hx-swap="outerHTML" hx-push-url="' . e($target) . '" aria-label="' . e($label) . '" data-bs-toggle="tooltip" title="' . e($label) . '"><i class="' . e($icon) . '"></i></a>';
$lastGroup = null;
?>
<div class="col-lg-12" id="schedule-results">
    <div class="card" id="schedule-card">
        <div class="card-header">
            <h5 class="card-title" id="schedule-title"><?= e($gridStart->format('M j')) ?> – <?= e($gridEnd->modify('-1 day')->format('M j, Y')) ?></h5>
            <div class="hstack gap-2">
                <?= $navBtn('schedule-prev-btn', $prev, 'feather-chevron-left', 'Earlier') ?>
                <?= $navBtn('schedule-this-week-btn', $thisWeek, 'feather-calendar', 'This week') ?>
                <?= $navBtn('schedule-next-btn', $next, 'feather-chevron-right', 'Later') ?>
            </div>
        </div>
        <div class="card-body custom-card-action p-0">
            <div class="schedule-scroll">
                <table class="table table-bordered mb-0 schedule-table" id="schedule-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="schedule-resource" id="schedule-col-resource">Resource</th>
                            <?php foreach ($days as $day): $ymd = $day->format('Y-m-d'); $dow = (int) $day->format('N'); ?>
                                <th class="schedule-day<?= $dow >= 6 ? ' schedule-weekend' : '' ?><?= $ymd === $today ? ' schedule-today' : '' ?>" id="schedule-col-<?= e($day->format('Ymd')) ?>"<?= $ymd === $today ? ' title="Today"' : '' ?>>
                                    <?= $day->format('j') === '1' || $day === $days[0] ? '<span class="d-block text-muted">' . e($day->format('M')) . '</span>' : '' ?><?= e($day->format('j')) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="schedule-tbody">
                    <?php foreach ($resources as $key => $res):
                        $group = $res['resource_kind'] === 'vessel' ? 'Vessels' : 'Equipment';
                        $rid = str_replace(':', '-', $key);
                        $lanes = schedule_lanes($bookings[$key] ?? []);
                        $occupant = $res['resource_kind'] === 'vessel' ? ($occupants[(int) $res['resource_id']] ?? null) : null; ?>
                        <?php if ($group !== $lastGroup): $lastGroup = $group; ?>
                        <tr class="schedule-group" id="schedule-group-<?= e(strtolower($group)) ?>"><td colspan="<?= e($dayCount + 1) ?>" class="fw-bold text-muted"><?= e($group) ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($lanes as $laneNo => $lane): ?>
                        <tr id="schedule-row-<?= e($rid) ?>-<?= e($laneNo) ?>">
                            <?php if ($laneNo === 0): ?>
                            <td class="schedule-resource" rowspan="<?= e(count($lanes)) ?>" id="schedule-row-<?= e($rid) ?>-name">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <a class="fw-semibold text-dark" id="schedule-row-<?= e($rid) ?>-link" <?= nav_attrs($res['resource_kind'] === 'vessel' ? '/vessels/' . (int) $res['resource_id'] . '/edit' : '/equipment/' . (int) $res['resource_id']) ?>><?= e($res['name']) ?></a>
                                        <div class="fs-11 text-muted"><?= e(humanize($res['kind'])) ?><?= $res['resource_kind'] === 'vessel' ? ' · ' . e(fmt_qty($res['capacity_l'], 'L', 0)) : '' ?><?= $res['status'] === 'out_of_service' ? ' · <span class="text-danger">out of service</span>' : ($res['status'] === 'cleaning' ? ' · <span class="text-warning">cleaning</span>' : '') ?></div>
                                        <?php if ($occupant !== null): ?><div class="fs-11" id="schedule-row-<?= e($rid) ?>-occupant"><?= badge('Now: ' . $occupant['label'], 'info') ?></div><?php endif; ?>
                                    </div>
                                    <?php if ($canEdit && $res['status'] !== 'out_of_service'): ?><a class="avatar-text avatar-xs" id="schedule-row-<?= e($rid) ?>-reserve-btn" data-bs-toggle="tooltip" title="Reserve" <?= nav_attrs('/reservations/new?resource=' . $key . '&on=' . ($today >= $windowStart && $today <= $windowLast ? $today : $windowStart)) ?>><i class="feather-plus"></i></a><?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                            <?php
                            $i = 0;
                            $byStart = [];
                            foreach ($lane as $b) {
                                $startIdx = max(0, (int) $gridStart->diff(new DateTimeImmutable($b['local_from'], $tz))->format('%r%a'));
                                $byStart[$startIdx] = $b;
                            }
                            while ($i < $dayCount):
                                $day = $days[$i]; $ymd = $day->format('Y-m-d'); $dow = (int) $day->format('N');
                                $cellClass = ($dow >= 6 ? ' schedule-weekend' : '') . ($ymd === $today ? ' schedule-today' : '');
                                if (isset($byStart[$i])):
                                    $b = $byStart[$i];
                                    $endIdx = min($dayCount - 1, (int) $gridStart->diff(new DateTimeImmutable($b['local_to'], $tz))->format('%r%a'));
                                    $span = max(1, $endIdx - $i + 1);
                                    $color = schedule_bar_color($b);
                                    $cut = ($b['local_from'] < $windowStart ? ' schedule-bar-cut-start' : '') . ($b['local_to'] > $windowLast ? ' schedule-bar-cut-end' : '');
                                    $flags = ((int) $b['clash_count'] > 0 || $b['shared'] ? ' schedule-bar-shared' : '') . ($b['resource_status'] === 'out_of_service' ? ' schedule-bar-out' : '');
                                    $title = schedule_bar_text($b) . ' — ' . reservation_window_label($b) . ((int) $b['clash_count'] > 0 ? ' — overlaps ' . (int) $b['clash_count'] . ' other booking' . ((int) $b['clash_count'] === 1 ? '' : 's') : '') . ($b['shared'] ? ' — shared' : ''); ?>
                                    <td colspan="<?= e($span) ?>" class="<?= e(trim($cellClass)) ?>" id="schedule-cell-<?= e($rid) ?>-<?= e($day->format('Ymd')) ?>">
                                        <a class="schedule-bar bg-soft-<?= e($color) ?> text-<?= e($color) ?><?= $flags . $cut ?>" id="schedule-bar-<?= e((int) $b['id']) ?>" data-bs-toggle="tooltip" title="<?= e($title) ?>" <?= nav_attrs(schedule_bar_url($b)) ?>><?= $b['all_day'] ? '' : '<i class="feather-clock me-1"></i>' ?><?= e(schedule_bar_text($b)) ?></a>
                                    </td>
                                    <?php $i += $span;
                                else: ?>
                                    <td class="<?= e(trim($cellClass)) ?>" id="schedule-cell-<?= e($rid) ?>-<?= e($day->format('Ymd')) ?>"></td>
                                    <?php $i++;
                                endif;
                            endwhile; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ($resources === []): ?><tr id="schedule-empty"><td colspan="<?= e($dayCount + 1) ?>" class="text-center text-muted py-5"><i class="feather-inbox fs-1 d-block mb-3"></i><?= $query['subject'] !== null ? 'Nothing booked for this run in these weeks.' : 'No active vessels or equipment match.' ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer fs-12 text-muted" id="schedule-footer">A bar is a booking; click it to open the run. An amber edge means it overlaps another booking on the same resource (shared when the organization allows it); a red edge means the resource is out of service. A dashed end runs on past the window. <?= badge('Now: …', 'info') ?> is what a vessel holds today.</div>
    </div>
</div>
