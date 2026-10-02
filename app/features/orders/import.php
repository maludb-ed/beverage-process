<?php
declare(strict_types=1);

// Importing customer orders from a spreadsheet (CSV, XLSX, XLS): read the first sheet, map its columns to
// order fields, validate every row before anything is written, then create the orders in one transaction.
// Past-due rows become closed history fulfilled outside the system; future rows become confirmed (or draft).
// An import can be undone while none of its orders has packaging runs or shipments.

require_once __DIR__ . '/queries.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

const ORDER_IMPORT_MAX_ROWS = 5000;
const ORDER_IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const ORDER_IMPORT_EXTENSIONS = ['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xls' => 'application/vnd.ms-excel'];
const ORDER_IMPORT_STATUSES = ['previewed' => 'Previewed', 'imported' => 'Imported', 'undone' => 'Undone', 'abandoned' => 'Abandoned'];
const ORDER_IMPORT_STATUS_COLORS = ['previewed' => 'warning', 'imported' => 'success', 'undone' => 'secondary', 'abandoned' => 'secondary'];
/** Order fields a column can map to: label, required, header names recognised (compared lowercase, letters and digits only). */
const ORDER_IMPORT_FIELDS = [
    'reference'  => ['Order reference', false, ['orderreference', 'reference', 'ref', 'order', 'ordernumber', 'orderno', 'order#', 'po', 'ponumber', 'customerpo', 'customerreference', 'invoice', 'invoicenumber']],
    'customer'   => ['Customer', true, ['customer', 'customername', 'account', 'accountname', 'client', 'soldto', 'shipto']],
    'ordered_on' => ['Ordered on', false, ['orderedon', 'orderdate', 'ordered', 'dateordered', 'date']],
    'due_on'     => ['Due on', true, ['dueon', 'duedate', 'due', 'shipdate', 'requested', 'requestedon', 'requesteddate', 'deliverydate', 'deliveryon']],
    'format'     => ['Product and format', true, ['format', 'package', 'packaging', 'packagingconfiguration', 'sku', 'item', 'itemcode', 'productformat']],
    'product'    => ['Product', false, ['product', 'productname', 'cider', 'brand']],
    'units'      => ['Units', true, ['units', 'qty', 'quantity', 'unitsordered', 'count']],
    'unit_price' => ['Price per unit', false, ['unitprice', 'price', 'priceperunit', 'rate']],
    'status'     => ['Status', false, ['status', 'orderstatus', 'state']],
    'notes'      => ['Notes', false, ['notes', 'note', 'comments', 'comment', 'memo']],
];
const ORDER_IMPORT_HISTORY_WORDS = ['history', 'closed', 'shipped', 'fulfilled', 'complete', 'completed', 'delivered', 'invoiced', 'paid'];
const ORDER_IMPORT_CONFIRMED_WORDS = ['confirmed', 'open', 'accepted', 'booked'];
const ORDER_IMPORT_DRAFT_WORDS = ['draft', 'pending', 'quote', 'tentative'];
const ORDER_IMPORT_SKIP_WORDS = ['cancelled', 'canceled', 'void', 'voided'];

/** The template's header row and two example rows (one past, one upcoming). */
function orders_import_template_rows(): array
{
    return [
        ['Order reference', 'Customer', 'Ordered on', 'Due on', 'Product', 'Format', 'Units', 'Price per unit', 'Status', 'Notes'],
        ['PO-1042', 'The Hill Taproom', '2026-05-01', '2026-05-08', 'Hill Dry Cider', 'Dry half barrel', '4', '165.00', '', 'Past order: imports as history'],
        ['PO-1042', 'The Hill Taproom', '2026-05-01', '2026-05-08', 'Hill Dry Cider', 'Dry 16 oz can case', '96', '1.85', '', 'Same reference: same order'],
        ['PO-2001', 'Green Mountain Distributors', '2026-10-01', '2026-10-20', 'Hill Dry Cider', 'Dry 16 oz can case', '480', '', 'confirmed', 'Blank price: none recorded'],
    ];
}

/**
 * Read the first sheet: [headers (index => text), rows (spreadsheet row number => [index => value])].
 * Date cells come back as Y-m-d; blank rows are dropped. Throws RuntimeException on an unreadable file.
 */
function orders_import_read_file(string $path, string $extension): array
{
    try {
        $reader = IOFactory::createReader(match ($extension) { 'csv' => 'Csv', 'xlsx' => 'Xlsx', 'xls' => 'Xls' });
        if ($extension !== 'csv') {
            $reader->setReadDataOnly(false);
        }
        $sheet = $reader->load($path)->getSheet(0);
    } catch (Throwable $exception) {
        throw new RuntimeException('The file could not be read as a spreadsheet: ' . $exception->getMessage());
    }
    $headers = [];
    $rows = [];
    foreach ($sheet->getRowIterator() as $row) {
        $values = [];
        $iterator = $row->getCellIterator();
        $iterator->setIterateOnlyExistingCells(false);
        $col = 0;
        foreach ($iterator as $cell) {
            $value = $cell->getCalculatedValue();
            if ($value !== null && $value !== '' && is_numeric($value) && SpreadsheetDate::isDateTime($cell)) {
                $value = SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
            $values[$col++] = is_string($value) ? trim($value) : ($value === null ? '' : (string) $value);
            if ($col > 40) {
                break;
            }
        }
        if ($headers === []) {
            $headers = array_filter($values, static fn($v) => $v !== '');
            continue;
        }
        if (implode('', $values) === '') {
            continue;
        }
        $rows[$row->getRowIndex()] = $values;
        if (count($rows) > ORDER_IMPORT_MAX_ROWS) {
            throw new RuntimeException('The file has more than ' . ORDER_IMPORT_MAX_ROWS . ' rows; split it into smaller files.');
        }
    }
    if ($headers === []) {
        throw new RuntimeException('The first row must hold column headers.');
    }
    return [$headers, $rows];
}

function orders_import_header_key(string $header): string
{
    return preg_replace('/[^a-z0-9#]/', '', mb_strtolower($header));
}

/** Field => header text: the previous import's mapping where those headers exist, else recognised header names. */
function orders_import_guess_mapping(array $headers, array $previous = []): array
{
    $mapping = [];
    $byNorm = [];
    foreach ($headers as $header) {
        $byNorm[orders_import_header_key($header)] ??= $header;
    }
    foreach (ORDER_IMPORT_FIELDS as $field => [, , $names]) {
        if (isset($previous[$field]) && in_array($previous[$field], $headers, true)) {
            $mapping[$field] = $previous[$field];
            continue;
        }
        foreach ($names as $name) {
            if (isset($byNorm[$name]) && !in_array($byNorm[$name], $mapping, true)) {
                $mapping[$field] = $byNorm[$name];
                break;
            }
        }
    }
    return $mapping;
}

/** A date in the forms spreadsheets use (Y-m-d, m/d/Y, m/d/y, d-M-Y, "May 1, 2026"), as Y-m-d, or null. */
function orders_import_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    foreach (['!Y-m-d', '!n/j/Y', '!n/j/y', '!m/d/Y', '!m/d/y', '!j-M-Y', '!j-M-y', '!M j, Y', '!F j, Y', '!Y/m/d'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

/**
 * Validate every row against the mapping. Returns:
 *  orders: key => [customer_id|null, customer_name, reference, ordered_on, due_on, kind (history|confirmed|draft), first_row, lines => [[row, configuration_id, units, unit_price, notes]]]
 *  rows: row number => [result (ok|skip|error), message, values (field => text)]
 *  new_customers: lowercase name => display name
 *  counts: rows, ok, skipped, errors, orders, history, confirmed, draft, lines
 */
function orders_import_validate(PDO $pdo, array $headers, array $rows, array $mapping, string $futureStatus): array
{
    $index = [];
    foreach ($mapping as $field => $header) {
        $col = array_search($header, $headers, true);
        if ($col !== false && isset(ORDER_IMPORT_FIELDS[$field])) {
            $index[$field] = $col;
        }
    }
    $missing = [];
    foreach (ORDER_IMPORT_FIELDS as $field => [$label, $required]) {
        if ($required && !isset($index[$field])) {
            $missing[] = $label;
        }
    }
    $customers = [];
    foreach ($pdo->query('SELECT id, name FROM app.customers') as $c) {
        $customers[mb_strtolower(trim($c['name']))] = ['id' => (int) $c['id'], 'name' => $c['name']];
    }
    $formats = $pdo->query(<<<'SQL'
        SELECT pc.id, lower(pc.name) AS name, lower(i.code) AS item_code, lower(p.name) AS product, lower(p.code) AS product_code
        FROM app.packaging_configurations pc JOIN app.products p ON p.id = pc.product_id JOIN app.items i ON i.id = pc.finished_item_id
    SQL)->fetchAll();
    $existing = $pdo->prepare("SELECT number FROM app.sales_orders WHERE customer_id = :c AND lower(customer_reference) = lower(:r) AND status <> 'cancelled'");
    $today = today();
    $out = ['orders' => [], 'rows' => [], 'new_customers' => [], 'missing' => $missing,
            'counts' => ['rows' => count($rows), 'ok' => 0, 'skipped' => 0, 'errors' => 0, 'orders' => 0, 'history' => 0, 'confirmed' => 0, 'draft' => 0, 'lines' => 0]];
    if ($missing !== []) {
        foreach ($rows as $n => $values) {
            $out['rows'][$n] = ['result' => 'error', 'message' => 'Map a column to: ' . implode(', ', $missing) . '.', 'values' => []];
        }
        $out['counts']['errors'] = count($rows);
        return $out;
    }
    $skippedGroups = [];
    foreach ($rows as $n => $values) {
        $get = static fn(string $field) => isset($index[$field]) ? trim((string) ($values[$index[$field]] ?? '')) : '';
        $v = array_map($get, array_combine(array_keys(ORDER_IMPORT_FIELDS), array_keys(ORDER_IMPORT_FIELDS)));
        $errors = [];
        $customerName = $v['customer'];
        $customer = $customers[mb_strtolower($customerName)] ?? null;
        if ($customerName === '') { $errors[] = 'Customer is blank.'; }
        $due = orders_import_date($v['due_on']);
        if ($due === null) { $errors[] = $v['due_on'] === '' ? 'Due date is blank.' : 'Due date "' . $v['due_on'] . '" is not a date.'; }
        $ordered = $v['ordered_on'] === '' ? ($due === null ? null : min($due, $today)) : orders_import_date($v['ordered_on']);
        if ($ordered === null && $v['ordered_on'] !== '') { $errors[] = 'Order date "' . $v['ordered_on'] . '" is not a date.'; }
        if ($ordered !== null && $due !== null && $due < $ordered) { $errors[] = 'Due date is before the order date.'; }
        // Format: configuration name or finished item code, narrowed by product name or code when given.
        $want = mb_strtolower($v['format']);
        $product = mb_strtolower($v['product']);
        $matches = array_values(array_filter($formats, static fn($f) => ($f['name'] === $want || $f['item_code'] === $want)
            && ($product === '' || $f['product'] === $product || $f['product_code'] === $product)));
        $configId = count($matches) === 1 ? (int) $matches[0]['id'] : null;
        if ($v['format'] === '') { $errors[] = 'Format is blank.'; }
        elseif ($matches === []) { $errors[] = 'No format "' . $v['format'] . '"' . ($v['product'] !== '' ? ' for product "' . $v['product'] . '"' : '') . '.'; }
        elseif ($configId === null) { $errors[] = 'Format "' . $v['format'] . '" matches several products; add a Product column.'; }
        $unitsRaw = str_replace(',', '', $v['units']);
        $units = preg_match('/^\d+(\.0+)?$/', $unitsRaw) ? (int) $unitsRaw : null;
        if ($units === null || $units < 1) { $errors[] = 'Units must be a whole number, 1 or more.'; }
        $priceRaw = str_replace(['$', ','], '', $v['unit_price']);
        $price = $priceRaw === '' ? null : (is_numeric($priceRaw) && (float) $priceRaw >= 0 ? round((float) $priceRaw, 2) : false);
        if ($price === false) { $errors[] = 'Price "' . $v['unit_price'] . '" is not a price.'; }
        $status = mb_strtolower($v['status']);
        $kind = match (true) {
            $status === '' => $due !== null && $due < $today ? 'history' : $futureStatus,
            in_array($status, ORDER_IMPORT_HISTORY_WORDS, true) => 'history',
            in_array($status, ORDER_IMPORT_CONFIRMED_WORDS, true) => 'confirmed',
            in_array($status, ORDER_IMPORT_DRAFT_WORDS, true) => 'draft',
            in_array($status, ORDER_IMPORT_SKIP_WORDS, true) => 'skip',
            default => null,
        };
        if ($kind === null) { $errors[] = 'Status "' . $v['status'] . '" is not one of: history, confirmed, draft, cancelled.'; }
        if ($errors !== []) {
            $out['rows'][$n] = ['result' => 'error', 'message' => implode(' ', $errors), 'values' => $v];
            $out['counts']['errors']++;
            continue;
        }
        if ($kind === 'skip') {
            $out['rows'][$n] = ['result' => 'skip', 'message' => 'Cancelled in the file.', 'values' => $v];
            $out['counts']['skipped']++;
            continue;
        }
        $key = mb_strtolower($customerName) . '|' . ($v['reference'] !== '' ? 'r:' . mb_strtolower($v['reference']) : 'd:' . $ordered . '|' . $due);
        if (isset($skippedGroups[$key])) {
            $out['rows'][$n] = ['result' => 'skip', 'message' => $skippedGroups[$key], 'values' => $v];
            $out['counts']['skipped']++;
            continue;
        }
        if ($customer !== null && $v['reference'] !== '' && !isset($out['orders'][$key])) {
            $existing->execute(['c' => $customer['id'], 'r' => $v['reference']]);
            if (($number = $existing->fetchColumn()) !== false) {
                $skippedGroups[$key] = 'Already in the system as ' . $number . '.';
                $out['rows'][$n] = ['result' => 'skip', 'message' => $skippedGroups[$key], 'values' => $v];
                $out['counts']['skipped']++;
                continue;
            }
        }
        if (isset($out['orders'][$key])) {
            $order = $out['orders'][$key];
            if ($order['due_on'] !== $due || $order['ordered_on'] !== $ordered || $order['kind'] !== $kind) {
                $out['rows'][$n] = ['result' => 'error', 'message' => 'Same order as row ' . $order['first_row'] . ' but a different date or status.', 'values' => $v];
                $out['counts']['errors']++;
                continue;
            }
        } else {
            $out['orders'][$key] = ['customer_id' => $customer['id'] ?? null, 'customer_name' => $customer['name'] ?? $customerName, 'reference' => $v['reference'],
                                    'ordered_on' => $ordered, 'due_on' => $due, 'kind' => $kind, 'first_row' => $n, 'lines' => []];
            if ($customer === null) {
                $out['new_customers'][mb_strtolower($customerName)] = $customerName;
            }
        }
        $out['orders'][$key]['lines'][] = ['row' => $n, 'configuration_id' => $configId, 'units' => $units, 'unit_price' => $price, 'notes' => mb_substr($v['notes'], 0, 500)];
        $out['rows'][$n] = ['result' => 'ok', 'message' => ($customer === null ? 'New customer. ' : '') . ucfirst($kind === 'history' ? 'history (fulfilled outside the system)' : $kind) . '.', 'values' => $v];
        $out['counts']['ok']++;
    }
    $out['counts']['orders'] = count($out['orders']);
    foreach ($out['orders'] as $order) {
        $out['counts'][$order['kind']]++;
        $out['counts']['lines'] += count($order['lines']);
    }
    return $out;
}

function insert_order_import(PDO $pdo, int $attachmentId, array $mapping, string $futureStatus, int $rowsTotal, int $userId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.order_imports (number, attachment_id, mapping, future_status, rows_total, created_by)
        VALUES (app.next_number('order_import'), :a, CAST(:m AS jsonb), :f, :t, :by)
        RETURNING id, number, status
    SQL);
    $statement->execute(['a' => $attachmentId, 'm' => json_encode($mapping, JSON_THROW_ON_ERROR), 'f' => $futureStatus, 't' => $rowsTotal, 'by' => $userId]);
    return $statement->fetch();
}

function update_order_import_mapping(PDO $pdo, int $id, array $mapping, string $futureStatus): void
{
    $pdo->prepare("UPDATE app.order_imports SET mapping = CAST(:m AS jsonb), future_status = :f WHERE id = :id AND status = 'previewed'")
        ->execute(['id' => $id, 'm' => json_encode($mapping, JSON_THROW_ON_ERROR), 'f' => $futureStatus]);
}

function find_order_import(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT oi.*, a.file_name, a.storage_path, a.mime_type, a.byte_size, uc.display_name AS created_by_name, ui.display_name AS imported_by_name,
               uu.display_name AS undone_by_name,
               (SELECT count(*) FROM app.sales_orders so WHERE so.order_import_id = oi.id) AS orders_created
        FROM app.order_imports oi
        JOIN app.attachments a ON a.id = oi.attachment_id
        LEFT JOIN app.users uc ON uc.id = oi.created_by
        LEFT JOIN app.users ui ON ui.id = oi.imported_by
        LEFT JOIN app.users uu ON uu.id = oi.undone_by
        WHERE oi.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $row['mapping'] = json_decode((string) $row['mapping'], true) ?: [];
    $row['errors'] = json_decode((string) $row['errors'], true) ?: [];
    return $row;
}

function find_order_imports(PDO $pdo, int $limit = 25): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT oi.id, oi.number, oi.status, oi.rows_total, oi.rows_imported, oi.rows_skipped, oi.rows_failed, oi.created_at, oi.imported_at,
               a.file_name, u.display_name AS created_by_name,
               (SELECT count(*) FROM app.sales_orders so WHERE so.order_import_id = oi.id) AS orders_created
        FROM app.order_imports oi JOIN app.attachments a ON a.id = oi.attachment_id LEFT JOIN app.users u ON u.id = oi.created_by
        ORDER BY oi.id DESC LIMIT :n
    SQL);
    $statement->bindValue('n', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

/** The latest committed mapping, to prefill the next upload. */
function orders_import_previous_mapping(PDO $pdo): array
{
    $value = $pdo->query("SELECT mapping FROM app.order_imports WHERE status IN ('imported', 'undone') ORDER BY id DESC LIMIT 1")->fetchColumn();
    return $value === false ? [] : (json_decode((string) $value, true) ?: []);
}

function find_import_orders(PDO $pdo, int $importId, int $limit = 200): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT so.id, so.number, so.status, so.requested_on, so.customer_reference, so.import_row, c.name AS customer_name,
               (SELECT SUM(l.units_ordered) FROM app.sales_order_lines l WHERE l.sales_order_id = so.id) AS units,
               (SELECT SUM(l.line_total) FROM app.sales_order_lines l WHERE l.sales_order_id = so.id) AS order_value
        FROM app.sales_orders so JOIN app.customers c ON c.id = so.customer_id
        WHERE so.order_import_id = :id ORDER BY so.import_row, so.id LIMIT :n
    SQL);
    $statement->bindValue('id', $importId, PDO::PARAM_INT);
    $statement->bindValue('n', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

/**
 * Create the validated orders. Rows with errors are recorded as failed (the caller allows that only when the user
 * chose to skip them). New customers are created when $createCustomers, otherwise their rows fail.
 * Returns ['orders' => n, 'customers' => n, 'failed' => n].
 */
function orders_import_commit(PDO $pdo, array $import, array $result, bool $createCustomers, int $userId): array
{
    $premisesId = $pdo->query('SELECT id FROM app.premises WHERE active ORDER BY id LIMIT 1')->fetchColumn()
        ?: throw new RuntimeException('Set up a premises before importing orders.');
    $customerIds = [];
    $customersCreated = 0;
    $insertCustomer = $pdo->prepare("INSERT INTO app.customers (name, kind, notes) VALUES (:n, 'other', :notes) RETURNING id");
    $failedRows = [];
    foreach ($result['rows'] as $n => $row) {
        if ($row['result'] === 'error') {
            $failedRows[$n] = $row['message'];
        }
    }
    $insertOrder = $pdo->prepare(<<<'SQL'
        INSERT INTO app.sales_orders (number, customer_id, premises_id, status, origin, destination_kind, ordered_on, requested_on, customer_reference,
                                      fulfilled_outside, order_import_id, import_row, created_by, confirmed_by, confirmed_at, closed_by, closed_at)
        VALUES (app.next_number('sales_order'), :c, :p, :status, 'imported', :dest, :ordered, :due, :ref, :outside, :import, :row, :by,
                CASE WHEN :status = 'confirmed' THEN CAST(:by AS bigint) END, CASE WHEN :status = 'confirmed' THEN now() END,
                CASE WHEN :outside THEN CAST(:by AS bigint) END, CASE WHEN :outside THEN now() END)
        RETURNING id
    SQL);
    $insertLine = $pdo->prepare('INSERT INTO app.sales_order_lines (sales_order_id, line_no, packaging_configuration_id, units_ordered, unit_price, notes) VALUES (:o, :n, :c, :u, :p, :notes)');
    $destination = $pdo->prepare('SELECT default_destination FROM app.customers WHERE id = :id');
    $orders = 0;
    foreach ($result['orders'] as $order) {
        $customerId = $order['customer_id'];
        if ($customerId === null) {
            $key = mb_strtolower($order['customer_name']);
            if (!$createCustomers) {
                foreach ($order['lines'] as $line) {
                    $failedRows[$line['row']] = 'New customer "' . $order['customer_name'] . '" not created.';
                }
                continue;
            }
            if (!isset($customerIds[$key])) {
                $insertCustomer->execute(['n' => mb_substr($order['customer_name'], 0, 200), 'notes' => 'Created by order import ' . $import['number'] . '.']);
                $customerIds[$key] = (int) $insertCustomer->fetchColumn();
                $customersCreated++;
            }
            $customerId = $customerIds[$key];
        }
        $destination->execute(['id' => $customerId]);
        $outside = $order['kind'] === 'history';
        $insertOrder->execute([
            'c' => $customerId, 'p' => $premisesId, 'status' => $outside ? 'closed' : $order['kind'],
            'dest' => order_default_destination(['default_destination' => $destination->fetchColumn()]),
            'ordered' => $order['ordered_on'], 'due' => $order['due_on'], 'ref' => $order['reference'] !== '' ? mb_substr($order['reference'], 0, 60) : null,
            'outside' => $outside ? 't' : 'f', 'import' => $import['id'], 'row' => $order['first_row'], 'by' => $userId,
        ]);
        $orderId = (int) $insertOrder->fetchColumn();
        foreach (array_values($order['lines']) as $i => $line) {
            $insertLine->execute(['o' => $orderId, 'n' => $i + 1, 'c' => $line['configuration_id'], 'u' => $line['units'], 'p' => $line['unit_price'], 'notes' => $line['notes'] ?: null]);
        }
        $orders++;
    }
    $errorList = [];
    foreach ($failedRows as $n => $message) {
        $errorList[] = ['row' => $n, 'message' => $message];
    }
    $imported = $result['counts']['ok'] - count(array_filter(array_keys($failedRows), static fn($n) => ($result['rows'][$n]['result'] ?? '') === 'ok'));
    $pdo->prepare(<<<'SQL'
        UPDATE app.order_imports SET status = 'imported', rows_imported = :i, rows_skipped = :s, rows_failed = :f, errors = CAST(:e AS jsonb),
               customers_created = :cc, imported_by = :by, imported_at = now()
        WHERE id = :id AND status = 'previewed'
    SQL)->execute(['id' => $import['id'], 'i' => $imported, 's' => $result['counts']['skipped'], 'f' => count($failedRows),
                   'e' => json_encode($errorList, JSON_THROW_ON_ERROR), 'cc' => $customersCreated, 'by' => $userId]);
    return ['orders' => $orders, 'customers' => $customersCreated, 'failed' => count($failedRows), 'imported_rows' => $imported];
}

/** Delete an import's orders, refused once any of them has packaging runs, shipments or a production plan. Customers it created stay. */
function orders_import_undo(PDO $pdo, int $importId, int $userId): int
{
    $blocked = $pdo->prepare(<<<'SQL'
        SELECT so.number FROM app.sales_orders so JOIN app.sales_order_lines ol ON ol.sales_order_id = so.id
        WHERE so.order_import_id = :id AND (
              EXISTS (SELECT 1 FROM app.packaging_run_order_lines x WHERE x.sales_order_line_id = ol.id)
           OR EXISTS (SELECT 1 FROM app.removal_lines x WHERE x.sales_order_line_id = ol.id)
           OR EXISTS (SELECT 1 FROM app.production_order_packages x WHERE x.sales_order_line_id = ol.id)
           OR EXISTS (SELECT 1 FROM app.removals r WHERE r.sales_order_id = so.id))
        LIMIT 1
    SQL);
    $blocked->execute(['id' => $importId]);
    if (($number = $blocked->fetchColumn()) !== false) {
        throw new RuntimeException('Order ' . $number . ' from this import has packaging runs or shipments; the import cannot be undone.');
    }
    $deleted = $pdo->prepare('DELETE FROM app.sales_orders WHERE order_import_id = :id');
    $deleted->execute(['id' => $importId]);
    $statement = $pdo->prepare("UPDATE app.order_imports SET status = 'undone', undone_by = :by, undone_at = now() WHERE id = :id AND status = 'imported'");
    $statement->execute(['id' => $importId, 'by' => $userId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Only an imported file can be undone.');
    }
    return $deleted->rowCount();
}

function discard_order_import(PDO $pdo, int $importId): bool
{
    $statement = $pdo->prepare("UPDATE app.order_imports SET status = 'abandoned' WHERE id = :id AND status = 'previewed'");
    $statement->execute(['id' => $importId]);
    return $statement->rowCount() === 1;
}
