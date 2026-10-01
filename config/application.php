<?php
declare(strict_types=1);

// Default configuration. Secrets and environment values are overridden by
// config/local.php (gitignored). Read with config('db.name').
$defaults = [
    'app' => [
        'name'        => 'Cidery',
        'base_url'    => 'http://127.0.0.1',
        'environment' => 'production',
        'timezone'    => 'America/New_York',
    ],
    'db' => [
        'host' => '127.0.0.1', 'port' => '5432', 'name' => 'cidery_dev', 'user' => 'cidery_app', 'password' => '',
    ],
    'security' => [
        'totp_key'            => '',   // 64 hex chars, libsodium secretbox key for TOTP seeds
        'action_token_key'    => '',   // HMAC key for assistant action tokens
        'dummy_password_hash' => '',   // bcrypt cost 12, timing equalizer for unknown emails
        'password_min_length' => 12,
        'lockout_attempts_per_email' => 5,
        'lockout_attempts_per_ip'    => 20,
        'lockout_window_minutes'     => 15,
        'pending_2fa_minutes'        => 10,
        'reset_token_minutes'        => 60,
        'invite_token_days'          => 7,
    ],
    'google' => ['client_id' => '', 'client_secret' => ''],
    'malumail' => ['api_key' => '', 'from' => 'noreply@example.com', 'from_name' => 'Cidery'],
    'assistant' => ['service_url' => 'http://127.0.0.1:8765'],
];

$localFile = __DIR__ . '/local.php';
$local = is_file($localFile) ? (array) require $localFile : [];

return array_replace_recursive($defaults, $local);
