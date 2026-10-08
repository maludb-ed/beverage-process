<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

// Pattern A fragment: the run select for one kind of run (the picker reloads it when the kind changes).
$user = require_role('production');
$kind = request_string('subject_kind', 30);
$kind = in_options($kind, RESERVATION_SUBJECT_KINDS) ? $kind : 'production_order';
echo view('reservations/partials/subject-options.php', ['subjectKind' => $kind, 'subjectOptions' => reservation_subject_options(db(), $kind), 'selected' => request_integer('subject_id'), 'errors' => []]);
