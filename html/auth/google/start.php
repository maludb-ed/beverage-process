<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
os_close_local_signin();            // os-adoption: the kernel signs people in while OS_ENABLED is on

no_store_headers();
if ((string) config('google.client_id') === '') {
    flash('warning', 'Google sign-in is not configured for this installation yet.');
    header('Location: /login', true, 303);
    exit;
}
$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => config('google.client_id'),
    'clientSecret' => config('google.client_secret'),
    'redirectUri'  => rtrim((string) config('app.base_url'), '/') . '/auth/google/callback',
]);
$nonce = bin2hex(random_bytes(16));
$url = $provider->getAuthorizationUrl(['scope' => ['openid', 'email', 'profile'], 'nonce' => $nonce]);
$_SESSION['oauth2_state']  = $provider->getState();
$_SESSION['oauth2_nonce']  = $nonce;
$_SESSION['oauth2_pkce']   = $provider->getPkceCode();
$_SESSION['oauth2_next']   = safe_next($_GET['next'] ?? '/');
header('Location: ' . $url, true, 302);
