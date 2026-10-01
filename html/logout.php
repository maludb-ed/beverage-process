<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_post();
verify_csrf();
logout_user();
header('Location: /login', true, 303);
