<?php
/** @var string $screen  @var string $url  CSV URL with the current filters  @var bool $oob */
$oob = $oob ?? false;
?>
<a id="<?= e($screen) ?>-export-btn" class="btn btn-light-brand" href="<?= e($url) ?>" download<?= $oob ? ' hx-swap-oob="true"' : '' ?>><i class="feather-download me-2"></i><span>Export CSV</span></a>
