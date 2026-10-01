<?php
declare(strict_types=1);
// Canonical batch release screen (/batches/{id}/release). The quality slice owns the
// controller in html/releases/batch.php; this route delegates to it.
require dirname(__DIR__) . '/releases/batch.php';
