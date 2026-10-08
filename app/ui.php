<?php
declare(strict_types=1);

// Markup helpers that emit the design-system's canonical snippets. Views call
// these; they never query. Every dynamic value passes through e().

/** Locked status-color vocabulary (docs/05-action-manifest.md). */
const STATUS_COLORS = [
    'active' => 'success', 'inactive' => 'secondary',
    'invited' => 'warning', 'disabled' => 'danger',
    'released' => 'success', 'hold' => 'warning', 'rejected' => 'danger', 'quarantine' => 'dark',
    'draft' => 'dark', 'open' => 'info', 'partial' => 'warning', 'closed' => 'success', 'closed_short' => 'danger', 'cancelled' => 'danger',
    'posted' => 'success', 'pending_approval' => 'warning', 'reversed' => 'danger',
    'counting' => 'info', 'review' => 'warning', 'approved' => 'success',
    'retired' => 'secondary', 'planned' => 'dark', 'in_progress' => 'info', 'complete' => 'success',
    'packaged' => 'success', 'dumped' => 'danger',
    'empty' => 'success', 'in_use' => 'info', 'cleaning' => 'warning', 'out_of_service' => 'danger', 'available' => 'success', 'booked' => 'info',
    'filled' => 'info', 'at_customer' => 'info', 'returned_dirty' => 'warning', 'lost' => 'danger',
    'final' => 'info', 'filed' => 'success',
    'received' => 'success',
    'not_required' => 'secondary', 'required' => 'warning', 'submitted' => 'info', 'expired' => 'danger',
];

function status_color(string $status): string
{
    return STATUS_COLORS[$status] ?? 'secondary';
}

function humanize(?string $value): string
{
    return $value === null ? '' : ucfirst(str_replace('_', ' ', $value));
}

function status_dot(string $color): string
{
    return '<span class="wd-10 ht-10 bg-' . e($color) . ' me-2 d-inline-block rounded-circle"></span>';
}

function badge(string $label, string $color = 'secondary', ?string $id = null): string
{
    return '<span class="badge bg-soft-' . e($color) . ' text-' . e($color) . '"' . ($id ? ' id="' . e($id) . '"' : '') . '>' . e($label) . '</span>';
}

function status_badge(string $status, ?string $id = null): string
{
    return badge(humanize($status), status_color($status), $id);
}

function yes_no(mixed $value): string
{
    return $value ? 'Yes' : 'No';
}

/** Attributes for an HTMX navigation link into #page-content with an explicit URL push. */
function nav_attrs(string $url): string
{
    return 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
}

/** Page-header button that navigates to a screen. */
function nav_button(string $id, string $url, string $label, string $icon = 'feather-plus', string $class = 'btn btn-primary'): string
{
    return '<a id="' . e($id) . '" class="' . e($class) . '" ' . nav_attrs($url) . '><i class="' . e($icon) . ' me-2"></i><span>' . e($label) . '</span></a>';
}

/** Row Edit icon button (CRUD contract). */
function row_edit_button(string $id, string $url): string
{
    return '<a id="' . e($id) . '" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit" ' . nav_attrs($url) . '><i class="feather-edit"></i></a>';
}

/** Save + Cancel for a form page header; Save submits the form by its id. */
function form_actions(string $formId, string $cancelUrl, string $saveLabel = 'Save'): string
{
    return '<a id="' . e($formId) . '-cancel-btn" class="btn btn-light-brand" ' . nav_attrs($cancelUrl) . '><i class="feather-x me-2"></i><span>Cancel</span></a>'
        . '<button type="submit" form="' . e($formId) . '" id="' . e($formId) . '-save-btn" class="btn btn-primary"><i class="feather-check me-2"></i><span>' . e($saveLabel) . '</span></button>';
}

/** Search box for a list page header; refreshes only the results region. */
function list_search(string $screen, string $url, string $q, string $placeholder, string $include = ''): string
{
    $includeAttr = $include !== '' ? ' hx-include="' . e($include) . '"' : '';
    return '<div class="input-group"><span class="input-group-text"><i class="feather-search"></i></span>'
        . '<input type="search" name="q" id="' . e($screen) . '-search" class="form-control" placeholder="' . e($placeholder) . '" aria-label="' . e($placeholder) . '" value="' . e($q) . '"'
        . ' hx-get="' . e($url) . '" hx-target="#' . e($screen) . '-results" hx-swap="outerHTML" hx-trigger="keyup changed delay:400ms, search"' . $includeAttr . ' /></div>';
}

/** A filter select for a list page header; refreshes only the results region. */
function list_filter(string $screen, string $name, string $url, array $options, ?string $selected, string $allLabel): string
{
    $html = '<select class="form-select" name="' . e($name) . '" id="' . e($screen) . '-filter-' . e(str_replace('_', '-', $name)) . '"'
        . ' hx-get="' . e($url) . '" hx-target="#' . e($screen) . '-results" hx-swap="outerHTML" hx-trigger="change" hx-include="#' . e($screen) . '-search">'
        . '<option value="">' . e($allLabel) . '</option>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ((string) $selected === (string) $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html . '</select>';
}

// Form rows: the col-lg-4 label / col-lg-8 control grid with icon prefixes.
// $prefix is the form's id prefix ("premises-form"); ids follow {prefix}-field-{name}.

function field_id(string $prefix, string $name): string
{
    return $prefix . '-field-' . str_replace('_', '-', $name);
}

function form_row_open(string $prefix, string $name, string $label, bool $last = false): string
{
    $id = field_id($prefix, $name);
    return '<div class="row ' . ($last ? 'mb-0' : 'mb-4') . ' align-items-center" id="' . e($id) . '-row"><div class="col-lg-4">'
        . '<label id="' . e($id) . '-label" for="' . e($id) . '" class="fw-semibold">' . e($label) . ':</label></div><div class="col-lg-8">';
}

function form_row_close(?string $help = null): string
{
    return ($help !== null ? '<div class="fs-11 text-muted mt-1">' . e($help) . '</div>' : '') . '</div></div>';
}

function invalid_class(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' is-invalid' : '';
}

function form_input(string $prefix, string $name, string $label, mixed $value, array $errors = [], array $opts = []): string
{
    $type = $opts['type'] ?? 'text';
    $icon = $opts['icon'] ?? 'feather-edit-3';
    $attrs = '';
    foreach (['required', 'readonly', 'disabled', 'autofocus'] as $flag) {
        if (!empty($opts[$flag])) {
            $attrs .= ' ' . $flag;
        }
    }
    foreach (['min', 'max', 'step', 'maxlength', 'placeholder', 'pattern', 'inputmode', 'list'] as $attr) {
        if (isset($opts[$attr])) {
            $attrs .= ' ' . $attr . '="' . e($opts[$attr]) . '"';
        }
    }
    $id = field_id($prefix, $name);
    $inputName = $opts['name'] ?? $name;
    return form_row_open($prefix, $name, $label, !empty($opts['last']))
        . '<div class="input-group"><div class="input-group-text"><i class="' . e($icon) . '"></i></div>'
        . (isset($opts['suffix']) ? '' : '')
        . '<input type="' . e($type) . '" class="form-control' . invalid_class($errors, $name) . '" id="' . e($id) . '" name="' . e($inputName) . '" value="' . e($value) . '"' . $attrs . ' />'
        . (isset($opts['suffix']) ? '<div class="input-group-text">' . e($opts['suffix']) . '</div>' : '')
        . '</div>'
        . (isset($errors[$name]) ? '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>' : '')
        . form_row_close($opts['help'] ?? null);
}

/** $options: value => label. */
function form_select(string $prefix, string $name, string $label, array $options, mixed $selected, array $errors = [], array $opts = []): string
{
    $id = field_id($prefix, $name);
    $attrs = (!empty($opts['required']) ? ' required' : '') . (!empty($opts['disabled']) ? ' disabled' : '') . ($opts['extra'] ?? '');
    $html = form_row_open($prefix, $name, $label, !empty($opts['last']))
        . '<select class="form-select' . invalid_class($errors, $name) . '" id="' . e($id) . '" name="' . e($opts['name'] ?? $name) . '"' . $attrs . '>';
    if (isset($opts['blank'])) {
        $html .= '<option value="">' . e($opts['blank']) . '</option>';
    }
    foreach ($options as $value => $optionLabel) {
        $html .= '<option value="' . e($value) . '"' . ((string) $selected === (string) $value ? ' selected' : '') . '>' . e($optionLabel) . '</option>';
    }
    return $html . '</select>'
        . (isset($errors[$name]) ? '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>' : '')
        . form_row_close($opts['help'] ?? null);
}

function form_textarea(string $prefix, string $name, string $label, mixed $value, array $errors = [], array $opts = []): string
{
    $id = field_id($prefix, $name);
    return form_row_open($prefix, $name, $label, !empty($opts['last']))
        . '<textarea class="form-control' . invalid_class($errors, $name) . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . e($opts['rows'] ?? 3) . '"'
        . (isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '') . (!empty($opts['disabled']) ? ' disabled' : '') . '>' . e($value) . '</textarea>'
        . (isset($errors[$name]) ? '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>' : '')
        . form_row_close($opts['help'] ?? null);
}

function form_checkbox(string $prefix, string $name, string $label, bool $checked, array $opts = []): string
{
    $id = field_id($prefix, $name);
    return form_row_open($prefix, $name, $label, !empty($opts['last']))
        . '<div class="form-check form-switch"><input type="hidden" name="' . e($name) . '" value="0" />'
        . '<input class="form-check-input" type="checkbox" role="switch" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . (!empty($opts['disabled']) ? ' disabled' : '') . ' /></div>'
        . form_row_close($opts['help'] ?? null);
}

/** A read-only label/value row in a form. */
function form_static(string $prefix, string $name, string $label, string $html, bool $last = false): string
{
    return form_row_open($prefix, $name, $label, $last) . '<div id="' . e(field_id($prefix, $name)) . '" class="fw-semibold">' . $html . '</div>' . form_row_close();
}

/** Definition rows on a detail tab: muted label / semibold value. */
function detail_row(string $id, string $label, string $html, bool $last = false): string
{
    return '<div class="row g-0 ' . ($last ? '' : 'mb-4') . '" id="' . e($id) . '"><div class="col-sm-6 text-muted">' . e($label) . ':</div><div class="col-sm-6 fw-semibold">' . $html . '</div></div>';
}

// Request parsing for forms -----------------------------------------------------------

function post_bool(string $name): bool
{
    $value = $_POST[$name] ?? null;
    if (is_array($value)) {
        $value = end($value);
    }
    return in_array((string) $value, ['1', 'on', 'true', 'yes'], true);
}

/** A decimal from the request, or null when empty; false when malformed. */
function post_decimal(string $name): float|null|false
{
    $raw = trim((string) ($_POST[$name] ?? ''));
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '', $raw);
    return is_numeric($raw) ? (float) $raw : false;
}

function post_date(string $name): string|null|false
{
    $raw = trim((string) ($_POST[$name] ?? ''));
    if ($raw === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    return $d && $d->format('Y-m-d') === $raw ? $raw : false;
}

/** Validate a value against an allowlist of option keys. */
function in_options(mixed $value, array $options): bool
{
    return array_key_exists((string) $value, $options);
}

/** PostgreSQL unique violation (SQLSTATE 23505). */
function is_unique_violation(PDOException $exception): bool
{
    return ($exception->errorInfo[0] ?? $exception->getCode()) === '23505';
}

/** A check/raise from a trigger or constraint (SQLSTATE 23514 check_violation, P0001 raise). */
function db_error_message(PDOException $exception): ?string
{
    $state = $exception->errorInfo[0] ?? (string) $exception->getCode();
    if (in_array($state, ['23514', 'P0001', '23000'], true)) {
        $message = $exception->errorInfo[2] ?? $exception->getMessage();
        $message = preg_replace('/^ERROR:\s*/', '', (string) $message);
        return trim(strtok($message, "\n"));
    }
    return null;
}

function today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d');
}

/** A record's history as counts by label ("3 orders, 1 removal"), for delete-or-deactivate messages. */
function history_summary(array $history): string
{
    return implode(', ', array_map(static fn($label, $n) => $n . ' ' . ($n === 1 ? rtrim($label, 's') : $label), array_keys($history), $history));
}
