<?php /** @var ?array $item  @var array $lots  @var array $input  @var array $errors */
$p = 'batch-addition-form';
$units = array_map(static fn($u) => $u['label'], $item['units'] ?? []);
echo form_select($p, 'lot', 'Lot', array_map(static fn($l) => $l['label'], $lots), $input['lot_id'] ?? '', $errors, [
    'name' => 'lot_id', 'blank' => $item === null ? 'Choose an item first' : ($lots === [] ? 'No released lots with stock' : 'Choose a lot'),
    'help' => $item !== null && !$item['lot_controlled'] ? 'This item is not lot-controlled; the first lot (FEFO) is used when none is chosen.' : 'Released lots with stock, first to expire first.',
]);
echo form_select($p, 'unit', 'Unit', $units, $input['unit'] ?? ($item['base_unit_code'] ?? ''), $errors, ['name' => 'unit', 'required' => true, 'blank' => $units === [] ? 'Choose an item first' : null]);
