<?php
declare(strict_types=1);

function is_htmx_request(): bool
{
    return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Render a view under app/views. Template names come from code, never from requests. */
function view(string $template, array $data = []): string
{
    $viewsRoot = realpath(__DIR__ . '/views');
    if ($viewsRoot === false) {
        throw new RuntimeException('View directory does not exist.');
    }
    $path = realpath($viewsRoot . '/' . ltrim($template, '/'));
    if ($path === false || !str_starts_with($path, $viewsRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Invalid view: {$template}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $path;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

/**
 * Render a screen: the fragment for HTMX requests, the full shell otherwise.
 * $screen is the manifest screen id; $entity/$recordId feed the command bar context.
 */
function render_screen(string $title, string $screen, string $fragmentHtml, ?string $entity = null, ?int $recordId = null): void
{
    $context = view('shared/screen-context.php', ['screen' => $screen, 'entity' => $entity, 'recordId' => $recordId]);
    if (is_htmx_request()) {
        header('Vary: HX-Request');
        $flashes = '';
        foreach (take_flashes() as $flash) {
            $flashes .= view('shared/flash.php', $flash);
        }
        echo $context . $flashes . $fragmentHtml;
        return;
    }
    echo view('layout.php', ['title' => $title, 'screen' => $screen, 'content' => $context . $fragmentHtml]);
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
}

function request_integer(string $name): ?int
{
    $value = $_POST[$name] ?? $_GET[$name] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered === false ? null : $filtered;
}

function request_string(string $name, int $maxLength = 500): string
{
    $value = trim((string) ($_POST[$name] ?? $_GET[$name] ?? ''));
    return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
}

function request_id(): string
{
    return (string) ($GLOBALS['__request_id'] ?? '');
}

/** Full-page redirect for normal requests, HX-Redirect for HTMX requests. */
function redirect(string $url): never
{
    if (is_htmx_request()) {
        header('HX-Redirect: ' . $url);
    } else {
        header('Location: ' . $url, true, 303);
    }
    exit;
}

/** HTMX client-side navigation into #page-content with an explicit URL push. */
function hx_location(string $path): never
{
    header('HX-Location: ' . json_encode(['path' => $path, 'target' => '#page-content', 'swap' => 'innerHTML'], JSON_THROW_ON_ERROR));
    exit;
}

function hx_trigger(string $event): void
{
    header('HX-Trigger: ' . $event);
}

function not_found(string $message = 'Not found.'): never
{
    http_response_code(404);
    if (is_htmx_request()) {
        echo view('shared/error.php', ['message' => $message]);
    } else {
        echo view('layout.php', ['title' => 'Not found', 'screen' => 'error', 'content' => view('shared/error.php', ['message' => $message])]);
    }
    exit;
}

function forbidden(string $message = 'You do not have permission to do that.'): never
{
    http_response_code(403);
    if (is_htmx_request()) {
        echo view('shared/error.php', ['message' => $message]);
    } else {
        echo view('layout.php', ['title' => 'Forbidden', 'screen' => 'error', 'content' => view('shared/error.php', ['message' => $message])]);
    }
    exit;
}

/** One-shot flash messages across a redirect. */
function flash(string $kind, string $message): void
{
    $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function no_store_headers(): void
{
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('M j, Y');
}

function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('M j, Y g:i A');
}

function client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}
