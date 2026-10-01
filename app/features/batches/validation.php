<?php
declare(strict_types=1);

// Pure helpers shared by the liquid-handling slices (press runs, batches, dispositions,
// and later packaging): numbers from repeating rows, datetime-local parsing in the
// client timezone, and the vessel interlock messages (I3 occupancy, I4 capacity).

/** A decimal from a raw request value: null when blank, false when malformed. */
function batches_num(mixed $value): float|null|false
{
    $raw = trim(str_replace(',', '', (string) $value));
    if ($raw === '') {
        return null;
    }
    return is_numeric($raw) ? (float) $raw : false;
}

/** A datetime-local value ("2026-10-01T14:05") in the app timezone, or null when invalid. */
function batches_parse_datetime(string $raw): ?DateTimeImmutable
{
    $raw = substr(str_replace(' ', 'T', trim($raw)), 0, 16);
    $at = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, new DateTimeZone((string) config('app.timezone')));
    return $at === false ? null : $at;
}

/** A stored timestamp (or now) as a datetime-local value in the app timezone. */
function batches_datetime_local(?string $value = null): string
{
    if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return $value;
    }
    $at = $value === null || $value === '' ? new DateTimeImmutable('now') : new DateTimeImmutable($value);
    return $at->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d\TH:i');
}

/** Display volume (gal for this client) to liters, 3 decimals. */
function batches_volume_to_l(float $display): float
{
    return round((float) from_display($display, 'L'), 3);
}

/** Liters to the display volume, 3 decimals for prefilled inputs. */
function batches_l_to_volume(float|string|null $liters): string
{
    return $liters === null || $liters === '' ? '' : (string) round((float) to_display($liters, 'L'), 3);
}

/** I3 message: "Vessel FV-1 holds B-26-001." */
function batches_occupied_message(array $vessel): string
{
    return 'Vessel ' . $vessel['name'] . ' holds ' . ($vessel['occupant_label'] ?? 'something') . '.';
}

/** I4 warning text, or null when the volume fits. */
function batches_capacity_warning(array $vessel, float $volumeL): ?string
{
    if ($volumeL <= (float) $vessel['capacity_l'] + 0.0005) {
        return null;
    }
    return 'Vessel ' . $vessel['name'] . ' holds ' . fmt_qty($vessel['capacity_l'], 'L') . '; ' . fmt_qty($volumeL, 'L') . ' is above its capacity.';
}

/** gal/ton and gal/bushel from a liters-per-kg yield. */
function batches_press_yield(float|string|null $litersPerKg): array
{
    if ($litersPerKg === null || $litersPerKg === '') {
        return ['gal_per_ton' => null, 'gal_per_bushel' => null];
    }
    $y = (float) $litersPerKg;
    return ['gal_per_ton' => $y / LITERS_PER_GALLON * KG_PER_TON, 'gal_per_bushel' => $y * 19.05087954 / LITERS_PER_GALLON];
}

/**
 * Batch status colors from the locked vocabulary (docs/05 "Status vocabulary": active info,
 * packaged success, dumped danger, closed secondary). STATUS_COLORS in app/ui.php maps the
 * shared words active/closed for other entities (users, purchase orders), so batches use this map.
 */
const BATCH_STATUS_COLORS = ['active' => 'info', 'packaged' => 'success', 'dumped' => 'danger', 'closed' => 'secondary'];

function batches_status_badge(string $status, ?string $id = null): string
{
    return badge(humanize($status), BATCH_STATUS_COLORS[$status] ?? 'secondary', $id);
}

/** Tax class badge: hard_cider success, other classes warning, unknown secondary. */
function batches_tax_class_badge(?string $taxClass, ?string $id = null): string
{
    if ($taxClass === null || $taxClass === '') {
        return badge('Unknown', 'secondary', $id);
    }
    return badge(humanize($taxClass), $taxClass === 'hard_cider' ? 'success' : 'warning', $id);
}

/** A spec range for display: "3.2–3.8", "≤ 0", "≥ 6". */
function batches_spec_range(float|string|null $min, float|string|null $max): string
{
    $fmt = static fn($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    if ($min !== null && $max !== null) {
        return $fmt($min) . '–' . $fmt($max);
    }
    return $min !== null ? '≥ ' . $fmt($min) : ($max !== null ? '≤ ' . $fmt($max) : '');
}

/** How far past now a form time may be (clock drift), in seconds. */
const BATCHES_FUTURE_TOLERANCE = 600;

/**
 * Parse a datetime-local form field for every liquid-handling form: sets $errors[$field] to
 * $missingMessage when blank/invalid, or "That time is in the future." when more than 10
 * minutes ahead of now, and returns null in both cases.
 */
function batches_datetime_field(string $raw, array &$errors, string $field, string $missingMessage): ?DateTimeImmutable
{
    $at = batches_parse_datetime($raw);
    if ($at === null) {
        $errors[$field] = $missingMessage;
        return null;
    }
    if ($at->getTimestamp() > time() + BATCHES_FUTURE_TOLERANCE) {
        $errors[$field] = 'That time is in the future.';
        return null;
    }
    return $at;
}
