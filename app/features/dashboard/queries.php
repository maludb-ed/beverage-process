<?php
declare(strict_types=1);

function dashboard_stats(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
        SELECT
            (SELECT count(*) FROM app.vessels WHERE active) AS vessels_total,
            (SELECT count(*) FROM app.vessel_occupancies WHERE to_at IS NULL) AS vessels_in_use,
            (SELECT count(*) FROM app.batches WHERE status = 'active') AS batches_active,
            (SELECT count(*) FROM app.lots WHERE quality_status IN ('quarantine','hold')) AS lots_quarantine,
            (SELECT count(*) FROM app.purchase_orders WHERE status IN ('open','partial')) AS po_open,
            (SELECT count(*) FROM app.purchase_orders WHERE status IN ('open','partial') AND expected_on < current_date) AS po_overdue
    SQL)->fetch();
}
