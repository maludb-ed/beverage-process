<?php
declare(strict_types=1);

/**
 * Send one email via MaluMail (api.malumail.com). Returns the decoded API response.
 * Throws RuntimeException on transport errors and non-2xx responses.
 */
function malumail_send(array $mail): array
{
    $ch = curl_init('https://api.malumail.com/v1/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . config('malumail.api_key'),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($mail, JSON_THROW_ON_ERROR),
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno  = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        throw new RuntimeException('MaluMail transport error.');
    }
    $decoded = json_decode((string) $body, true);
    if ($status !== 200) {
        throw new RuntimeException("MaluMail send failed ({$status}): " . ($decoded['error'] ?? 'unknown'));
    }
    return $decoded;
}

/**
 * Application-level send: renders app/views/emails/{template}.html.php and .txt.php,
 * sends through MaluMail, logs to the activity log. Without an API key (development)
 * the message is written to the error log instead and treated as sent.
 */
function send_mail(string $to, string $subject, string $template, array $data = []): bool
{
    $html = view('emails/' . $template . '.html.php', $data);
    $text = view('emails/' . $template . '.txt.php', $data);
    $details = ['to' => $to, 'subject' => $subject, 'template' => $template];
    try {
        if ((string) config('malumail.api_key') === '') {
            error_log("[mail:dev] to={$to} subject={$subject}\n{$text}");
            $details['mode'] = 'dev_log';
        } else {
            $result = malumail_send([
                'from' => config('malumail.from'), 'from_name' => config('malumail.from_name'),
                'to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $text,
            ]);
            $details['rejected'] = $result['rejected'] ?? [];
        }
        log_activity(db(), 'email_sent', 'email', null, $to, null, null, $details, null, 'system');
        return empty($details['rejected']);
    } catch (RuntimeException $exception) {
        error_log('send_mail failed: ' . $exception->getMessage());
        log_activity(db(), 'email_failed', 'email', null, $to, null, null, $details + ['error' => $exception->getMessage()], null, 'system');
        return false;
    }
}
