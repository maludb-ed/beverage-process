<?php
declare(strict_types=1);

// Application bootstrap: configuration, helpers, session, security headers.
// Every endpoint under /var/www/html requires this file first.

require_once dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['__config'] = require dirname(__DIR__) . '/config/application.php';

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) config('app.timezone', 'UTC'));

if (config('app.environment') === 'development') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/units.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/os.php';
require_once __DIR__ . '/json_mode.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/list.php';
require_once __DIR__ . '/ui.php';

// Unhandled exceptions: log the detail, show a plain error, never the message.
set_exception_handler(static function (Throwable $exception): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><title>Error</title><p>Something went wrong. The error has been logged.</p>';
    exit;
});

// Session: strict mode, cookie-only, HttpOnly, SameSite=Lax, Secure on HTTPS.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('SID');
session_start();

// Baseline security headers on every response.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$GLOBALS['__request_id'] = bin2hex(random_bytes(8));
if (isset($_SERVER['HTTP_X_REQUEST_ID']) && preg_match('/^[A-Za-z0-9._:-]{4,80}$/', (string) $_SERVER['HTTP_X_REQUEST_ID'])) {
    $GLOBALS['__request_id'] = (string) $_SERVER['HTTP_X_REQUEST_ID'];      // honoured: the kernel's trail joins ours
}

// JSON mode (mcp-and-api.md §3): the kernel's actions server POSTs to the handlers with Accept: application/json and a
// tenant token; the handler's HTMX answer (HX-Location, a re-rendered form, an error page) is translated at shutdown
// into {ok, location} / {error}. Never for a browser.
if (os_enabled() && !is_htmx_request() && isset($_SERVER['HTTP_X_ACTION_TOKEN'])
    && str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
    json_mode_begin();
}
