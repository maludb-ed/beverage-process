<?php
/** @var array $headers  @var array $rows  Plain CSV (header row then data rows), numbers already unformatted. */
$out = fopen('php://memory', 'w+');
fputcsv($out, $headers, ',', '"', '');
foreach ($rows as $row) {
    fputcsv($out, $row, ',', '"', '');
}
rewind($out);
echo stream_get_contents($out);
fclose($out);
