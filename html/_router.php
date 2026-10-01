<?php
declare(strict_types=1);
/**
 * Pretty-URL router. Maps the canonical URLs from the action manifest onto the
 * php-patterns page controllers under /var/www/html/{feature}/:
 *   /{feature}/                     -> {feature}/index.php
 *   /{feature}/new                  -> {feature}/form.php
 *   /{feature}/{id}                 -> {feature}/view.php?id=
 *   /{feature}/{id}/edit            -> {feature}/form.php?id=
 *   /{feature}/{id}/{verb}          -> {feature}/{verb}.php?id=  (POST prefers {verb}-save.php)
 *   /{feature}/{id}/{a}/{b}         -> {feature}/{a}-{b}.php?id=   ("new" becomes "form")
 *   /{feature}/{id}/{a}/{n}/{b}     -> {feature}/{a}-{b}.php?id=&sub_id=n
 *   /{feature}/{verb}               -> {feature}/{verb}.php
 * A feature with no controller yet renders the stub screen.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
$root = __DIR__;

$fixed = [
    '/login'            => 'login.php',
    '/logout'           => 'logout.php',
    '/login/2fa'        => 'auth/2fa.php',
    '/password/reset'   => 'auth/reset.php',
    '/auth/google/start'    => 'auth/google/start.php',
    '/auth/google/callback' => 'auth/google/callback.php',
    '/settings/profile' => 'settings/profile.php',
    '/settings/2fa'     => 'settings/2fa.php',
    '/assistant/message'    => 'assistant/message.php',
    '/assistant/transcript' => 'assistant/transcript.php',
    '/assistant/undo'       => 'assistant/undo.php',
];
if (isset($fixed[$path])) {
    require $root . '/' . $fixed[$path];
    return;
}
if (preg_match('#^/password/reset/([a-f0-9]{64})$#', $path, $m)) {
    $_GET['token'] = $m[1];
    require $root . '/auth/reset-confirm.php';
    return;
}
if (preg_match('#^/invite/([a-f0-9]{64})$#', $path, $m)) {
    $_GET['token'] = $m[1];
    require $root . '/auth/invite.php';
    return;
}

$segments = explode('/', ltrim($path, '/'));
$feature = preg_replace('/[^a-z0-9-]/', '', $segments[0] ?? '');
$featureDir = $root . '/' . $feature;

if ($feature !== '' && is_dir($featureDir)) {
    // /{feature}[/{id}][/{word}[/{sub_id}]...]: the first number is ?id=, a later
    // number is ?sub_id=, the words join with "-" to name the controller file.
    $rest = array_slice($segments, 1);
    $id = null;
    if ($rest !== [] && ctype_digit($rest[0])) {
        $id = (int) array_shift($rest);
        $_GET['id'] = $id;
    }
    $words = [];
    foreach ($rest as $segment) {
        if ($segment === '') {
            continue;
        }
        if (ctype_digit($segment)) {
            $_GET['sub_id'] = (int) $segment;
            continue;
        }
        $words[] = preg_replace('/[^a-z0-9-]/', '', $segment);
    }
    if ($words === []) {
        $name = $id === null ? 'index' : 'view';
    } else {
        if (end($words) === 'new') {
            array_pop($words);
            $words[] = 'form';
        }
        if ($words === ['edit']) {
            $words = ['form'];
        }
        $name = implode('-', $words);
    }
    // A POST to /{feature}/{id}/{verb} prefers {verb}-save.php when the GET form lives in {verb}.php.
    $candidates = $_SERVER['REQUEST_METHOD'] === 'POST' && $name !== 'save' ? [$name . '-save', $name] : [$name];
    foreach ($candidates as $candidate) {
        if (is_file($featureDir . '/' . $candidate . '.php')) {
            require $featureDir . '/' . $candidate . '.php';
            return;
        }
    }
}

// No controller: a planned feature renders its stub, anything else is a 404.
require $root . '/stub.php';
