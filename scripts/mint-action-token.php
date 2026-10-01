<?php
declare(strict_types=1);
/**
 * Mint an assistant action token for a user (testing the actions MCP server by hand).
 * Usage: php scripts/mint-action-token.php <user_id> [ttl_seconds]
 * Prints the token only. Run on the host as a user who can read config/local.php.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
$GLOBALS['__config'] = require dirname(__DIR__) . '/config/application.php';
require_once dirname(__DIR__) . '/app/db.php';
require_once dirname(__DIR__) . '/app/http.php';
require_once dirname(__DIR__) . '/app/csrf.php';
require_once dirname(__DIR__) . '/app/activity.php';
require_once dirname(__DIR__) . '/app/auth.php';
function config(string $key, mixed $default = null): mixed { $v = $GLOBALS['__config']; foreach (explode('.', $key) as $p) { if (!is_array($v) || !array_key_exists($p, $v)) { return $default; } $v = $v[$p]; } return $v; }

$userId = (int) ($argv[1] ?? 0);
$ttl = (int) ($argv[2] ?? 300);
if ($userId < 1 || $ttl < 1 || $ttl > 3600) {
    fwrite(STDERR, "Usage: php scripts/mint-action-token.php <user_id> [ttl_seconds, at most 3600]\n");
    exit(1);
}
echo mint_action_token($userId, $ttl), "\n";
