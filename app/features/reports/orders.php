<?php
declare(strict_types=1);

// Order history report: customer orders by customer, product, format or month, with units and value.
// Counts confirmed, in-fulfillment, shipped and closed orders (history included); drafts and cancelled orders do not.

const REPORT_ORDER_GROUP_BY = ['customer' => 'Customer', 'product' => 'Product', 'format' => 'Format', 'month' => 'Month'];
const REPORT_ORDER_SORTS = ['group' => 'group_label', 'orders' => 'orders', 'units' => 'units', 'value' => 'value'];
const REPORT_ORDER_STATUSES = ['confirmed', 'in_fulfillment', 'shipped', 'closed'];

/** Rows by group between two due dates: group_label, group_key, orders, units, units_shipped, value, priced_units. */
function find_order_history_rows(PDO $pdo, string $groupBy, string $dateFrom, string $dateTo, ?int $customerId, string $sort = '-units'): array
{
    [$label, $key] = match ($groupBy) {
        'product' => ['l.product_name', 'l.product_id::text'],
        'format' => ["l.product_name || ' — ' || l.configuration_name", 'l.packaging_configuration_id::text'],
        'month' => ["to_char(date_trunc('month', l.requested_on), 'YYYY-MM')", "to_char(date_trunc('month', l.requested_on), 'YYYY-MM')"],
        default => ['l.customer_name', 'l.customer_id::text'],
    };
    $statement = $pdo->prepare(
        "SELECT {$label} AS group_label, {$key} AS group_key, COUNT(DISTINCT l.sales_order_id) AS orders, SUM(l.units_ordered) AS units,
                SUM(l.units_shipped) AS units_shipped, SUM(l.line_total) AS value, SUM(CASE WHEN l.unit_price IS NOT NULL THEN l.units_ordered ELSE 0 END) AS priced_units
         FROM app.v_sales_order_lines l
         WHERE l.order_status = ANY (string_to_array(:statuses, ',')) AND l.line_status <> 'cancelled'
           AND l.requested_on BETWEEN :from AND :to AND (CAST(:customer AS bigint) IS NULL OR l.customer_id = :customer)
         GROUP BY 1, 2 ORDER BY " . order_by($sort, REPORT_ORDER_SORTS, '-units') . ', 1'
    );
    $statement->execute(['statuses' => implode(',', REPORT_ORDER_STATUSES), 'from' => $dateFrom, 'to' => $dateTo, 'customer' => $customerId]);
    return $statement->fetchAll();
}

function report_order_customers(PDO $pdo): array
{
    return array_column($pdo->query('SELECT id, name FROM app.customers ORDER BY name')->fetchAll(), 'name', 'id');
}
