<?php
declare(strict_types=1);

// List-screen helpers: paging, sort allowlists, query strings.

const LIST_PAGE_SIZE = 25;

/**
 * Run a paged SELECT. $sql has no LIMIT/OFFSET; $countSql returns one integer.
 * Returns ['rows', 'total', 'page', 'page_size', 'pages'].
 */
function paged_query(PDO $pdo, string $sql, string $countSql, array $params, int $page, int $pageSize = LIST_PAGE_SIZE): array
{
    $count = $pdo->prepare($countSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = max(1, min($page, $pages));
    $statement = $pdo->prepare($sql . ' LIMIT :__limit OFFSET :__offset');
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR));
    }
    $statement->bindValue('__limit', $pageSize, PDO::PARAM_INT);
    $statement->bindValue('__offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
    $statement->execute();
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'page_size' => $pageSize, 'pages' => $pages];
}

/**
 * Resolve a sort request ("name" or "-name") against an allowlist of
 * request keys => SQL expressions. Returns an ORDER BY fragment.
 */
function order_by(string $requested, array $allowed, string $default): string
{
    $desc = str_starts_with($requested, '-');
    $key = ltrim($requested, '-');
    if (!array_key_exists($key, $allowed)) {
        $desc = str_starts_with($default, '-');
        $key = ltrim($default, '-');
    }
    return $allowed[$key] . ($desc ? ' DESC' : ' ASC') . ' NULLS LAST';
}

/** Read the common list parameters from the request. */
function list_params(string $defaultSort): array
{
    return [
        'q'    => request_string('q', 100),
        'sort' => request_string('sort', 40) ?: $defaultSort,
        'page' => max(1, request_integer('page') ?? 1),
    ];
}

/** Build a query string from non-empty values. */
function query_string(array $params): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '' && $v !== false);
    return $params === [] ? '' : '?' . http_build_query($params);
}

/** True when an HTMX request targets an inner results region (search, paging, filters). */
function is_results_request(string $resultsId): bool
{
    return is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === $resultsId;
}
