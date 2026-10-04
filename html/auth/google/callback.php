<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
os_close_local_signin();            // os-adoption: the kernel signs people in while OS_ENABLED is on

no_store_headers();
$pdo = db();
$ip = client_ip();
[$perEmail, $perIp, $window] = throttle_config();
$fail = static function (string $why) use ($pdo, $ip): never {
    record_login_attempt($pdo, null, $ip, 'google', false);
    error_log('google sign-in refused: ' . $why);
    flash('error', 'Google sign-in did not succeed. Try again or use your password.');
    header('Location: /login', true, 303);
    exit;
};

if ((string) config('google.client_id') === '' || too_many_attempts($pdo, null, $ip, $perEmail, $perIp, $window)) {
    $fail('not configured or throttled');
}
$state = $_SESSION['oauth2_state'] ?? null;
$nonce = $_SESSION['oauth2_nonce'] ?? null;
$pkce  = $_SESSION['oauth2_pkce'] ?? null;
$next  = safe_next($_SESSION['oauth2_next'] ?? '/');
unset($_SESSION['oauth2_state'], $_SESSION['oauth2_nonce'], $_SESSION['oauth2_pkce'], $_SESSION['oauth2_next']);   // one use only
if ($state === null || !hash_equals($state, (string) ($_GET['state'] ?? '')) || empty($_GET['code'])) {
    $fail('state mismatch');
}

$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => config('google.client_id'),
    'clientSecret' => config('google.client_secret'),
    'redirectUri'  => rtrim((string) config('app.base_url'), '/') . '/auth/google/callback',
]);
try {
    $provider->setPkceCode((string) $pkce);
    $token = $provider->getAccessToken('authorization_code', ['code' => (string) $_GET['code']]);
    $idToken = $token->getValues()['id_token'] ?? null;
    if ($idToken === null) {
        $fail('no id_token');
    }
    // Validate the ID token against Google's JWKS, issuer, audience, expiry and nonce.
    $claims = google_validate_id_token((string) $idToken, (string) config('google.client_id'), (string) $nonce);
    $owner = $provider->getResourceOwner($token);
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
if (empty($claims['email_verified']) || empty($claims['sub']) || empty($claims['email'])) {
    $fail('email not verified');
}
$sub   = (string) $claims['sub'];
$email = normalize_email((string) $claims['email']);
$name  = trim((string) ($claims['name'] ?? $owner->getName() ?? $email));

$user = null;
$identity = find_identity($pdo, 'google', $sub);
if ($identity !== null) {
    $user = find_user($pdo, (int) $identity['user_id']);                          // rule 1: identity match
} else {
    $existing = find_user_by_email($pdo, $email);
    if ($existing !== null) {
        if (user_has_identity($pdo, (int) $existing['id'], 'google')) {
            $fail('email belongs to a user with a different Google identity');       // rule 4: never merge
        }
        insert_identity($pdo, (int) $existing['id'], 'google', $sub, $email);         // rule 2: auto-link
        log_activity($pdo, 'identity_linked', 'user', (int) $existing['id'], $existing['display_name'], null, null, ['provider' => 'google'], 'login',
            'screen', (int) $existing['id'], 'user/' . $existing['id'] . ' ' . $existing['display_name']);
        $user = find_user($pdo, (int) $existing['id']);
    } else {
        // rule 3: a new account. Until the owner assigns a role it is a viewer; invited users are the norm.
        $pdo->beginTransaction();
        $created = insert_user($pdo, $email, $name !== '' ? $name : $email, 'viewer', 'active', null, true);
        insert_identity($pdo, (int) $created['id'], 'google', $sub, $email);
        log_activity($pdo, 'user_created_via_google', 'user', (int) $created['id'], $created['display_name'], null, null, [], 'login',
            'screen', (int) $created['id'], 'user/' . $created['id'] . ' ' . $created['display_name']);
        $pdo->commit();
        $user = find_user($pdo, (int) $created['id']);
    }
}
if ($user === null || $user['status'] !== 'active') {
    $fail('user not active');
}
record_login_attempt($pdo, $email, $ip, 'google', true);
$target = begin_session_for($user, 'google', $next);
header('Location: ' . $target, true, 303);

/** Minimal OIDC ID-token validation: RS256 signature via Google's JWKS, iss, aud, exp, nonce. */
function google_validate_id_token(string $jwt, string $audience, string $nonce): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed id_token');
    }
    $b64 = static fn(string $s): string => (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    $header = json_decode($b64($parts[0]), true);
    $claims = json_decode($b64($parts[1]), true);
    if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        throw new RuntimeException('unexpected alg');
    }
    $jwks = json_decode((string) file_get_contents('https://www.googleapis.com/oauth2/v3/certs'), true);
    $key = null;
    foreach ($jwks['keys'] ?? [] as $candidate) {
        if (($candidate['kid'] ?? null) === $header['kid']) {
            $key = $candidate;
        }
    }
    if ($key === null) {
        throw new RuntimeException('unknown kid');
    }
    $pem = jwk_rsa_to_pem($key['n'], $key['e']);
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $b64($parts[2]), $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new RuntimeException('bad signature');
    }
    if (!in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
        || ($claims['aud'] ?? '') !== $audience
        || ($claims['exp'] ?? 0) < time()
        || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
        throw new RuntimeException('claims rejected');
    }
    return $claims;
}

function jwk_rsa_to_pem(string $n, string $e): string
{
    $b64url = static fn(string $s): string => (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    $der = static function (string $tag, string $value): string {
        $len = strlen($value);
        if ($len < 128) { return $tag . chr($len) . $value; }
        $lenBytes = ltrim(pack('N', $len), "\0");
        return $tag . chr(0x80 | strlen($lenBytes)) . $lenBytes . $value;
    };
    $int = static function (string $raw) use ($der): string {
        if (ord($raw[0]) > 0x7f) { $raw = "\0" . $raw; }
        return $der("\x02", $raw);
    };
    $rsaKey = $der("\x30", $int($b64url($n)) . $int($b64url($e)));
    $algId  = $der("\x30", "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00");
    $spki   = $der("\x30", $algId . $der("\x03", "\0" . $rsaKey));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
