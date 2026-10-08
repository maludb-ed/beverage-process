<?php /** @var array $resources  @var array $bookings  @var array $occupants  @var array $query  @var DateTimeImmutable $gridStart  @var DateTimeImmutable $gridEnd  @var string $today  @var bool $canEdit */
$tz = new DateTimeZone((string) config('app.timezone'));
$first = new DateTimeImmutable($query['month'] . '-01', $tz);
$last = $first->modify('last day of this month');
$link = static fn(array $overrides) => '/schedule/' . query_string(array_merge($query, $overrides));
$prev = $link(['month' => $first->modify('-1 month')->format('Y-m')]);
$next = $link(['month' => $first->modify('+1 month')->format('Y-m')]);
$thisMonth = $link(['month' => substr($today, 0, 7)]);
$navBtn = static fn(string $id, string $target, string $icon, string $label) => '<a class="btn btn-sm btn-light-brand" id="' . e($id) . '" href="' . e($target) . '" hx-get="' . e($target) . '" hx-target="#schedule-results" hx-swap="outerHTML" hx-push-url="' . e($target) . '" aria-label="' . e($label) . '" data-bs-toggle="tooltip" title="' . e($label) . '"><i class="' . e($icon) . '"></i></a>';
// Every booking by the days it covers inside the grid.
$byDay = [];
foreach ($bookings as $key => $list) {
    foreach ($list as $b) {
        for ($d = new DateTimeImmutable(max($b['local_from'], $gridStart->format('Y-m-d')), $tz); $d->format('Y-m-d') <= min($b['local_to'], $gridEnd->modify('-1 day')->format('Y-m-d')); $d = $d->modify('+1 day')) {
            $byDay[$d->format('Y-m-d')][] = $b;
        }
    }
}
$weeks = [];
for ($cursor = $gridStart; $cursor < $gridEnd; $cursor = $cursor->modify('+1 week')) { $weeks[] = $cursor; }
?>
<div class="col-lg-12" id="schedule-results">
    <div class="card" id="schedule-card">
        <div class="card-header">
            <h5 class="card-title" id="schedule-title"><?= e($first->format('F Y')) ?></h5>
            <div class="hstack gap-2">
                <?= $navBtn('schedule-prev-btn', $prev, 'feather-chevron-left', 'Previous month') ?>
                <?= $navBtn('schedule-this-month-btn', $thisMonth, 'feather-calendar', 'This month') ?>
                <?= $navBtn('schedule-next-btn', $next, 'feather-chevron-right', 'Next month') ?>
            </div>
        </div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 schedule-month-table" id="schedule-month-table">
                    <thead class="thead-light">
                        <tr><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?><th id="schedule-month-col-<?= e(strtolower($dow)) ?>"><?= e($dow) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody id="schedule-month-tbody">
                    <?php foreach ($weeks as $week): ?>
                        <tr id="schedule-month-week-<?= e($week->format('Ymd')) ?>">
                        <?php for ($d = 0; $d < 7; $d++):
                            $day = $week->modify('+' . $d . ' days'); $ymd = $day->format('Y-m-d');
                            $inMonth = $day >= $first && $day <= $last;
                            $cid = 'schedule-month-day-' . $day->format('Ymd'); ?>
                            <td id="<?= e($cid) ?>" class="<?= $inMonth ? '' : 'bg-light text-muted' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fs-12 fw-semibold<?= $inMonth ? '' : ' text-muted' ?>" id="<?= e($cid) ?>-number"><?= $ymd === $today ? badge($day->format('j'), 'primary') : e($day->format('j')) ?></span>
                                    <?php if ($canEdit && $inMonth): ?><a class="fs-11 text-muted" id="<?= e($cid) ?>-reserve" title="Reserve" <?= nav_attrs('/reservations/new?on=' . $ymd . ($query['resource'] !== null ? '&resource=' . $query['resource'] : '')) ?>><i class="feather-plus"></i></a><?php endif; ?>
                                </div>
                                <?php foreach ($byDay[$ymd] ?? [] as $b): $color = schedule_bar_color($b);
                                    $cont = $b['local_from'] < $ymd; ?>
                                    <a class="schedule-chip bg-soft-<?= e($color) ?> text-<?= e($color) ?><?= (int) $b['clash_count'] > 0 || $b['shared'] ? ' schedule-bar-shared' : '' ?>" id="<?= e($cid) ?>-booking-<?= e((int) $b['id']) ?>" data-bs-toggle="tooltip" title="<?= e(schedule_bar_text($b) . ' — ' . reservation_window_label($b)) ?>" <?= nav_attrs(schedule_bar_url($b)) ?>><?= $cont ? '<i class="feather-arrow-right me-1"></i>' : ($b['all_day'] ? '' : '<i class="feather-clock me-1"></i>') ?><?= e($b['resource_name']) ?> · <?= e($b['subject_number'] ?? humanize($b['kind'])) ?></a>
                                <?php endforeach; ?>
                            </td>
                        <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer fs-12 text-muted" id="schedule-footer">A chip is a booking on that day: the resource and the run; <i class="feather-arrow-right"></i> continues from an earlier day, <i class="feather-clock"></i> is a timed booking; an amber edge overlaps another booking. <?= $resources === [] ? 'No active vessels or equipment match.' : e(count($resources)) . ' resource' . (count($resources) === 1 ? '' : 's') . ' shown.' ?></div>
    </div>
</div>
