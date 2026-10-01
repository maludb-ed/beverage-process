<?php
declare(strict_types=1);

/** Fruit and catch-weight items are received by weigh tag. */
function receipt_needs_weigh_tag(?array $item): bool
{
    return $item !== null && ($item['item_class'] === 'fruit' || !empty($item['catch_weight']));
}

/**
 * Validate receipt line rows. Pure: takes raw arrays and the item catalog, returns
 * [cleanLines, errorsByRow]. Weigh tags arrive in the fruit display unit and are
 * returned in kg. Blank rows are skipped.
 */
function validate_receipt_lines(array $rawLines, array $catalog, int $actorId): array
{
    $lines = [];
    $errors = [];
    $num = static function ($value): ?float {
        $value = trim(str_replace([',', '$'], '', (string) $value));
        return $value === '' || !is_numeric($value) ? null : (float) $value;
    };
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $tagRaw = is_array($raw['weigh_tag'] ?? null) ? $raw['weigh_tag'] : [];
        if ($itemId === 0 && trim((string) ($raw['qty_received'] ?? '')) === '' && trim((string) ($tagRaw['gross'] ?? '')) === '') {
            continue;
        }
        $rowErrors = [];
        $item = $catalog[$itemId] ?? null;
        if ($item === null) {
            $rowErrors['item_id'] = 'Choose an item.';
        }
        $unit = (string) ($raw['purchase_unit_code'] ?? '');
        $factor = $item['units'][$unit]['factor'] ?? null;
        if ($item !== null && $factor === null) {
            $rowErrors['purchase_unit_code'] = 'Choose a unit for this item.';
        }
        $qty = $num($raw['qty_received'] ?? '');
        $price = $num($raw['unit_price'] ?? '') ?? 0.0;
        if ($price < 0) {
            $rowErrors['unit_price'] = 'Enter a price of zero or more.';
        }
        $weighTag = null;
        if (receipt_needs_weigh_tag($item)) {
            $gross = $num($tagRaw['gross'] ?? '');
            $tare = $num($tagRaw['tare'] ?? '') ?? 0.0;
            if ($gross === null || $gross <= 0) {
                $rowErrors['weigh_tag_gross'] = 'Enter the gross weight from the weigh tag.';
            } elseif ($tare < 0 || $tare >= $gross) {
                $rowErrors['weigh_tag_tare'] = 'Tare must be zero or more and below the gross weight.';
            }
            $brix = $num($tagRaw['brix'] ?? '');
            if ($brix !== null && ($brix < 0 || $brix > 40)) {
                $rowErrors['weigh_tag_brix'] = 'Brix should be between 0 and 40.';
            }
            $bins = $num($tagRaw['bin_count'] ?? '');
            $weighTag = [
                'tag_number' => trim((string) ($tagRaw['tag_number'] ?? '')) ?: null,
                'gross' => $gross, 'tare' => $tare,
                'gross_kg' => $gross !== null ? from_display($gross, 'kg', 'fruit') : null,
                'tare_kg' => from_display($tare, 'kg', 'fruit'),
                'bin_count' => $bins !== null ? (int) $bins : null,
                'variety' => trim((string) ($tagRaw['variety'] ?? '')) ?: null,
                'orchard' => trim((string) ($tagRaw['orchard'] ?? '')) ?: null,
                'block' => trim((string) ($tagRaw['block'] ?? '')) ?: null,
                'brix_at_receipt' => $brix,
                'condition_note' => trim((string) ($tagRaw['condition_note'] ?? '')) ?: null,
                'weighed_by' => $actorId,
            ];
            $qtyBase = $weighTag['gross_kg'] !== null ? max(0.0, $weighTag['gross_kg'] - $weighTag['tare_kg']) : 0.0;
            if ($qty === null && $factor) {
                $qty = $qtyBase / $factor;      // no count given: the net weight in the purchase unit
            }
        } else {
            if ($qty === null || $qty < 0) {
                $rowErrors['qty_received'] = 'Enter the quantity received.';
            }
            $qtyBase = ($qty ?? 0) * ($factor ?? 0);
        }
        $expires = trim((string) ($raw['expires_on'] ?? ''));
        if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            $rowErrors['expires_on'] = 'Use a valid date.';
        }
        $kind = (string) ($raw['discrepancy_kind'] ?? 'none');
        if (!in_options($kind, DISCREPANCY_KINDS)) {
            $kind = 'none';
        }
        // Cost per base unit: what the line cost divided by what arrived.
        $lineCost = ($qty ?? 0) * $price;
        $unitCostBase = $qtyBase > 0 ? $lineCost / $qtyBase : ($factor ? $price / $factor : 0.0);
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $poLine = (int) ($raw['purchase_order_line_id'] ?? 0);
        $lines[$n] = [
            'purchase_order_line_id' => $poLine ?: null, 'item_id' => $itemId ?: null, 'purchase_unit_code' => $unit, 'to_base_factor' => $factor,
            'qty_received' => $qty ?? ($raw['qty_received'] ?? ''), 'unit_price' => $price, 'qty_base' => $qtyBase, 'unit_cost_base' => $unitCostBase,
            'supplier_lot_number' => trim((string) ($raw['supplier_lot_number'] ?? '')) ?: null,
            'expires_on' => $expires !== '' ? $expires : null,
            'discrepancy_kind' => $kind, 'discrepancy_note' => trim((string) ($raw['discrepancy_note'] ?? '')) ?: null,
            'notes' => trim((string) ($raw['notes'] ?? '')) ?: null,
            'weigh_tag' => $weighTag,
        ];
    }
    return [$lines, $errors];
}
