<?php
declare(strict_types=1);
// php bin/dev_handoff.php <member_id> — prints a /sso URL signed exactly as the kernel signs one, from the fixture's
// claims (bin/dev_directory.json). Development only (testing-without-a-kernel.md); never served from html/.
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$member = (int) ($argv[1] ?? 1);
$key = action_token_key();
$app = os_app_key();
$payload = $member . '.' . (time() + 60) . '.' . $app . '.' . bin2hex(random_bytes(16));
$token = $payload . '.' . hash_hmac('sha256', 'sso:' . $payload, $key);
$fixture = json_decode((string) file_get_contents(__DIR__ . '/dev_directory.json'), true);
$claims = ($fixture['claims'][(string) $member] ?? []) + ['member_id' => $member, 'scope' => null];
$text = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
echo rtrim((string) config('app.base_url'), '/') . '/sso?' . http_build_query(['token' => $token, 'claims' => $text . '.' . hash_hmac('sha256', $text, $key)]) . "\n";
