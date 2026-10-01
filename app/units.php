<?php
declare(strict_types=1);

// Units and display conversion. Storage is base units (L, kg, ea). The UI shows
// the client's display units from app.client_settings: volume_display_unit for
// liters, mass_display_unit for kilograms, fruit_display_unit for fruit weights.

const LITERS_PER_GALLON = 3.785411784;
const KG_PER_POUND      = 0.45359237;
const KG_PER_TON        = 907.18474;

function client_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $row = db()->query('SELECT * FROM app.client_settings WHERE id = 1')->fetch();
        $settings = $row ?: ['client_name' => config('app.name'), 'volume_display_unit' => 'gal', 'mass_display_unit' => 'lb', 'fruit_display_unit' => 'lb', 'timezone' => config('app.timezone')];
    }
    return $settings;
}

/** All units keyed by code: ['code' => [name, dimension, to_base_factor, is_base]]. */
function unit_table(): array
{
    static $units = null;
    if ($units === null) {
        $units = [];
        foreach (db()->query('SELECT code, name, dimension, to_base_factor, is_base, display_order FROM app.units ORDER BY display_order') as $row) {
            $units[$row['code']] = $row;
        }
    }
    return $units;
}

function unit_factor(string $code): float
{
    return (float) (unit_table()[$code]['to_base_factor'] ?? 1.0);
}

/** The display unit for a base unit: $kind 'fruit' selects the fruit weight unit. */
function display_unit(string $baseUnit, string $kind = 'default'): string
{
    $settings = client_settings();
    return match ($baseUnit) {
        'L'  => (string) $settings['volume_display_unit'],
        'kg' => (string) ($kind === 'fruit' ? $settings['fruit_display_unit'] : $settings['mass_display_unit']),
        default => $baseUnit,
    };
}

function to_display(float|string|null $qtyBase, string $baseUnit, string $kind = 'default'): ?float
{
    if ($qtyBase === null || $qtyBase === '') {
        return null;
    }
    return (float) $qtyBase / unit_factor(display_unit($baseUnit, $kind));
}

function from_display(float|string|null $qtyDisplay, string $baseUnit, string $kind = 'default'): ?float
{
    if ($qtyDisplay === null || $qtyDisplay === '') {
        return null;
    }
    return (float) $qtyDisplay * unit_factor(display_unit($baseUnit, $kind));
}

function format_qty(float|string|null $value, int $decimals = 1): string
{
    return $value === null || $value === '' ? '' : number_format((float) $value, $decimals);
}

/** A base quantity rendered in display units with its unit code, e.g. "1,234.5 gal". */
function fmt_qty(float|string|null $qtyBase, string $baseUnit, int $decimals = 1, string $kind = 'default'): string
{
    if ($qtyBase === null || $qtyBase === '') {
        return '';
    }
    $unit = display_unit($baseUnit, $kind);
    $decimals = $unit === 'ea' || $unit === 'case' ? 0 : $decimals;
    return format_qty(to_display($qtyBase, $baseUnit, $kind), $decimals) . ' ' . $unit;
}

/** fmt_qty with the exact base value in a tooltip. */
function fmt_qty_html(float|string|null $qtyBase, string $baseUnit, int $decimals = 1, string $kind = 'default'): string
{
    if ($qtyBase === null || $qtyBase === '') {
        return '';
    }
    return '<span data-bs-toggle="tooltip" title="' . e(format_qty($qtyBase, 3) . ' ' . $baseUnit) . '">' . e(fmt_qty($qtyBase, $baseUnit, $decimals, $kind)) . '</span>';
}

/** Cost per base unit shown per display unit, e.g. "$1.23 / lb". */
function fmt_unit_cost(float|string|null $costPerBase, string $baseUnit, string $kind = 'default'): string
{
    if ($costPerBase === null || $costPerBase === '') {
        return '';
    }
    $unit = display_unit($baseUnit, $kind);
    return '$' . number_format((float) $costPerBase * unit_factor($unit), 4) . ' / ' . $unit;
}

function liters_to_gal(float|string|null $liters): ?float
{
    return $liters === null || $liters === '' ? null : (float) $liters / LITERS_PER_GALLON;
}

function gal_to_liters(float|string|null $gallons): ?float
{
    return $gallons === null || $gallons === '' ? null : (float) $gallons * LITERS_PER_GALLON;
}

function kg_to_lb(float|string|null $kg): ?float
{
    return $kg === null || $kg === '' ? null : (float) $kg / KG_PER_POUND;
}

function lb_to_kg(float|string|null $lb): ?float
{
    return $lb === null || $lb === '' ? null : (float) $lb * KG_PER_POUND;
}

/** Backwards-compatible alias used by Phase 2 views. */
function display_qty(float|string|null $baseQty, string $baseUnit, int $decimals = 1): string
{
    return fmt_qty($baseQty, $baseUnit, $decimals);
}
