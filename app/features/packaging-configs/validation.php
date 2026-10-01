<?php
declare(strict_types=1);

/**
 * Validate the repeating BOM rows of a packaging configuration. Pure: takes the raw
 * arrays and the item catalog (id => [code, name, base_unit_code]); returns
 * [cleanLines, errorsByRow]. Blank rows (no item and no quantity) are skipped.
 * Quantities are entered in the item's display unit and returned in its base unit.
 */
function validate_bom_lines(array $rawLines, array $catalog): array
{
    $lines = [];
    $errors = [];
    $seen = [];
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $qtyRaw = trim((string) ($raw['qty'] ?? ''));
        if ($itemId === 0 && $qtyRaw === '') {
            continue;
        }
        $rowErrors = [];
        $item = $catalog[$itemId] ?? null;
        if ($item === null) {
            $rowErrors['item_id'] = 'Choose an item.';
        } elseif (isset($seen[$itemId])) {
            $rowErrors['item_id'] = 'Each item can appear once.';
        } else {
            $seen[$itemId] = true;
        }
        $qty = is_numeric(str_replace(',', '', $qtyRaw)) ? (float) str_replace(',', '', $qtyRaw) : null;
        if ($qty === null || $qty <= 0) {
            $rowErrors['qty'] = 'Enter a quantity above zero.';
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $lines[$n] = [
            'item_id' => $itemId ?: null,
            'qty' => $qty ?? $qtyRaw,
            'qty_per_unit_base' => $item !== null && $qty !== null ? from_display($qty, $item['base_unit_code']) : null,
        ];
    }
    return [$lines, $errors];
}
