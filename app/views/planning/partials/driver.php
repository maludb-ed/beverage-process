<?php /** The demand type behind a suggestion. @var string $driver */
$map = ['planned' => ['Planned production', 'secondary'], 'firm' => ['Firm', 'success'], 'standing' => ['Standing', 'warning'], 'forecast' => ['Forecast', 'info']];
[$label, $color] = $map[$driver] ?? [humanize($driver), 'secondary']; ?>
<?= badge($label, $color) ?>
