<?php /** @var array $readback  @var ?array $batch  @var ?array $configuration */
$p = 'packaging-run-form';
$dash = '<span class="text-muted">—</span>';
$loss = $readback['loss_l'];
$over = $readback['loss_pct'] !== null && $readback['expected_loss_pct'] !== null && $readback['loss_pct'] > $readback['expected_loss_pct'];
?>
<?= form_static($p, 'readback_volume_out', 'Volume out', $readback['volume_out_l'] !== null ? fmt_qty_html($readback['volume_out_l'], 'L', 2) . ' <small class="text-muted">' . e($configuration['fill_volume_l'] ? number_format((float) liters_to_gal($configuration['fill_volume_l']), 3) . ' gal per unit' : '') . '</small>' : $dash) ?>
<?= form_static($p, 'readback_loss', 'Loss', $loss !== null ? ($loss < 0 ? '<span class="text-danger">Negative: units out hold more than the volume in</span>' : fmt_qty_html($loss, 'L', 2) . ' <small class="' . ($over ? 'text-danger' : 'text-muted') . '">' . e(number_format((float) $readback['loss_pct'], 2)) . '% of volume in' . ($readback['expected_loss_pct'] !== null ? ', expected ' . e(number_format((float) $readback['expected_loss_pct'], 2)) . '%' : '') . '</small>' . ($over ? ' ' . badge('Exceptional', 'danger') : '')) : $dash) ?>
<?= form_static($p, 'readback_tax_class', 'Derived tax class', $readback['tax_class'] !== null ? badge(humanize($readback['tax_class']), $readback['tax_class'] === 'hard_cider' ? 'success' : 'warning') . ($batch !== null && $batch['fruit_share_pct'] === null ? ' <small class="text-muted">batch has no fruit share, so hard cider cannot be derived</small>' : '') : $dash, true) ?>
