<?php
declare(strict_types=1);

// Central bootstrap: configuration, database, security helpers, services, layout.
// Errors are written to the PHP error log, never shown to the browser.
ini_set('display_errors', '0');
error_reporting(E_ALL);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    return $config;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/reset_service.php';
require_once __DIR__ . '/invite_service.php';
require_once __DIR__ . '/admin_layout.php';
require_once __DIR__ . '/layout.php';
