<?php
declare(strict_types=1);

// Request-reading helpers shared by the report controllers (kept out of queries.php).

/** A valid Y-m-d date from the request; $default when empty, absent or invalid. */
function reports_date_param(string $name, ?string $default = null): ?string
{
    $value = request_string($name, 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $value : $default;
}

function reports_default_date_from(): string
{
    return (new DateTimeImmutable('today'))->modify('-12 months')->format('Y-m-d');
}

/** Filter select options with a label => id style map; returns selected id or null when not an option. */
function reports_option_param(string $name, array $options): ?int
{
    $id = request_integer($name);
    return $id !== null && array_key_exists($id, $options) ? $id : null;
}

/** Page premises filter: only offered when more than one premises exists. */
function reports_premises_param(array $premises): ?int
{
    return count($premises) > 1 ? reports_option_param('premises_id', $premises) : null;
}

/** Number for CSV: unformatted, fixed decimals, empty for null. */
function reports_csv_num(float|string|null $value, int $decimals = 4): string
{
    return $value === null || $value === '' ? '' : number_format((float) $value, $decimals, '.', '');
}
