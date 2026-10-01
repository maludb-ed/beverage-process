<?php
declare(strict_types=1);
/**
 * Create the first owner account for a client database and print the invite link.
 * Usage: php scripts/create-owner.php <email> "<display name>"
 * Run on the host as a user who can read config/local.php.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
$GLOBALS['__config'] = require dirname(__DIR__) . '/config/application.php';
require_once dirname(__DIR__) . '/app/db.php';
require_once dirname(__DIR__) . '/app/http.php';
require_once dirname(__DIR__) . '/app/csrf.php';
require_once dirname(__DIR__) . '/app/activity.php';
require_once dirname(__DIR__) . '/app/auth.php';
function config(string $key, mixed $default = null): mixed { $v = $GLOBALS['__config']; foreach (explode('.', $key) as $p) { if (!is_array($v) || !array_key_exists($p, $v)) { return $default; } $v = $v[$p]; } return $v; }

$email = normalize_email((string) ($argv[1] ?? ''));
$name  = trim((string) ($argv[2] ?? ''));
if ($email === '' || $name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage: php scripts/create-owner.php <email> \"<display name>\"\n");
    exit(1);
}
$pdo = db();
$existing = find_user_by_email($pdo, $email);
if ($existing !== null) {
    fwrite(STDERR, "A user with that email already exists (id {$existing['id']}, status {$existing['status']}).\n");
    exit(1);
}
$days = (int) config('security.invite_token_days', 7);
$token = generate_token();
$pdo->beginTransaction();
$user = insert_user($pdo, $email, $name, 'owner', 'invited', null, false);
insert_one_time_token($pdo, (int) $user['id'], 'invite', token_hash($token), $days * 24 * 60);
log_activity($pdo, 'user_invited', 'user', (int) $user['id'], $name, null, ['role' => 'owner'], ['by' => 'create-owner script'], null, 'system', null, 'system/create-owner');
$pdo->commit();
echo "Owner created: {$email} (id {$user['id']})\n";
echo "Invite link (valid {$days} days):\n";
echo rtrim((string) config('app.base_url'), '/') . '/invite/' . $token . "\n";
