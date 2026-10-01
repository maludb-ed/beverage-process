<?php
declare(strict_types=1);

/**
 * Validate the repeating PO line rows from a form. Pure: takes the raw arrays,
 * returns [cleanLines, errorsByRow]. Blank rows (no item and no quantity) are skipped.
 */
function validate_po_lines(array $rawLines, array $catalog): array
{
    $lines = [];
    $errors = [];
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $qtyRaw = trim((string) ($raw['qty_ordered'] ?? ''));
        if ($itemId === 0 && $qtyRaw === '') {
            continue;
        }
        $rowErrors = [];
        $item = $catalog[$itemId] ?? null;
        if ($item === null) {
            $rowErrors['item_id'] = 'Choose an item.';
        }
        $unit = (string) ($raw['purchase_unit_code'] ?? '');
        if ($item !== null && !isset($item['units'][$unit])) {
            $rowErrors['purchase_unit_code'] = 'Choose a unit for this item.';
        }
        $qty = is_numeric(str_replace(',', '', $qtyRaw)) ? (float) str_replace(',', '', $qtyRaw) : null;
        if ($qty === null || $qty <= 0) {
            $rowErrors['qty_ordered'] = 'Enter a quantity above zero.';
        }
        $priceRaw = trim(str_replace(['$', ','], '', (string) ($raw['unit_price'] ?? '')));
        $price = $priceRaw === '' ? 0.0 : (is_numeric($priceRaw) ? (float) $priceRaw : null);
        if ($price === null || $price < 0) {
            $rowErrors['unit_price'] = 'Enter a price of zero or more.';
        }
        $expected = trim((string) ($raw['expected_on'] ?? ''));
        if ($expected !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected)) {
            $rowErrors['expected_on'] = 'Use a valid date.';
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $lines[$n] = [
            'item_id' => $itemId ?: null, 'purchase_unit_code' => $unit, 'qty_ordered' => $qty ?? $qtyRaw,
            'unit_price' => $price ?? $priceRaw, 'expected_on' => $expected !== '' ? $expected : null,
            'to_base_factor' => $item !== null && isset($item['units'][$unit]) ? $item['units'][$unit]['factor'] : null,
        ];
    }
    return [$lines, $errors];
}
