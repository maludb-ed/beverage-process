<?php
declare(strict_types=1);

/**
 * Validate transfer line rows against stock at the source location. Returns [cleanLines, errorsByRow].
 * Quantities arrive in display units and leave in base units. Blank rows are skipped.
 */
function validate_transfer_lines(PDO $pdo, array $rawLines, int $fromLocationId): array
{
    $lines = [];
    $errors = [];
    $itemIds = [];
    foreach ($rawLines as $raw) {
        if (is_array($raw)) { $itemIds[] = (int) ($raw['item_id'] ?? 0); }
    }
    $facts = inventory_item_facts($pdo, $itemIds);
    $claimed = [];   // lot id => base qty already used by earlier rows
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $lotId = (int) ($raw['lot_id'] ?? 0);
        $qtyRaw = trim(str_replace(',', '', (string) ($raw['qty'] ?? '')));
        if ($itemId === 0 && $lotId === 0 && $qtyRaw === '') {
            continue;
        }
        $rowErrors = [];
        $item = $facts[$itemId] ?? null;
        if ($item === null) { $rowErrors['item_id'] = 'Choose an item.'; }
        $balance = $item !== null && $lotId > 0 ? find_balance($pdo, $itemId, $lotId, $fromLocationId) : null;
        if ($item !== null && $balance === null) { $rowErrors['lot_id'] = 'Choose a lot that has stock at the source location.'; }
        $qtyBase = null;
        if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
            $rowErrors['qty'] = 'Enter a quantity above zero.';
        } elseif ($item !== null) {
            $qtyBase = round((float) from_display((float) $qtyRaw, $item['base_unit_code'], $item['kind']), 4);
            if ($qtyBase < 0.0001) {
                $rowErrors['qty'] = 'That quantity is too small.';
            } elseif ($balance !== null) {
                $available = round((float) $balance['qty_available'], 4) - ($claimed[$lotId] ?? 0.0);
                if ($qtyBase > $available + 0.00005) {
                    $rowErrors['qty'] = 'Only ' . fmt_qty($available, $item['base_unit_code'], 2, $item['kind']) . ' of ' . $balance['lot_number'] . ' is available here.';
                } else {
                    $claimed[$lotId] = ($claimed[$lotId] ?? 0.0) + $qtyBase;
                }
            }
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $lines[$n] = ['item_id' => $itemId ?: null, 'lot_id' => $lotId ?: null, 'qty' => $qtyRaw, 'qty_base' => $qtyBase];
    }
    return [$lines, $errors];
}
