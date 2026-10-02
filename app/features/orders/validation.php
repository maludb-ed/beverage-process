<?php
declare(strict_types=1);

/**
 * Validate the repeating order line rows from a form. Pure: takes the raw arrays and the format
 * catalog, returns [cleanLines, errorsByRow]. Blank rows (no format and no units) are skipped.
 * A blank price stays blank (no price recorded); $canPrice false keeps the posted price out entirely.
 */
function validate_order_lines(array $rawLines, array $catalog, bool $canPrice): array
{
    $lines = [];
    $errors = [];
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $configId = (int) ($raw['packaging_configuration_id'] ?? 0);
        $unitsRaw = trim(str_replace(',', '', (string) ($raw['units_ordered'] ?? '')));
        if ($configId === 0 && $unitsRaw === '') {
            continue;
        }
        $rowErrors = [];
        if (!isset($catalog[$configId])) {
            $rowErrors['packaging_configuration_id'] = 'Choose a product and format.';
        }
        $units = ctype_digit($unitsRaw) ? (int) $unitsRaw : null;
        if ($units === null || $units < 1) {
            $rowErrors['units_ordered'] = 'Enter whole units, 1 or more.';
        }
        $priceRaw = $canPrice ? trim(str_replace(['$', ','], '', (string) ($raw['unit_price'] ?? ''))) : '';
        $price = $priceRaw === '' ? null : (is_numeric($priceRaw) ? round((float) $priceRaw, 2) : false);
        if ($price === false || ($price !== null && $price < 0)) {
            $rowErrors['unit_price'] = 'Enter a price of zero or more, or leave it blank.';
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $lines[$n] = [
            'id' => ctype_digit((string) ($raw['id'] ?? '')) ? (int) $raw['id'] : null,
            'packaging_configuration_id' => $configId ?: null,
            'units_ordered' => $units ?? $unitsRaw,
            'unit_price' => $price === false ? $priceRaw : $price,
            'notes' => mb_substr(trim((string) ($raw['notes'] ?? '')), 0, 500),
        ];
    }
    return [$lines, $errors];
}
