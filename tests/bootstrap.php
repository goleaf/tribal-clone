<?php
declare(strict_types=1);

// Shared test bootstrap: clear logs once, then load the main app bootstrap.
require_once __DIR__ . '/clear_logs.php';

if (!defined('TEST_LOGS_CLEARED')) {
    clearProjectLogs(__DIR__ . '/..');
    define('TEST_LOGS_CLEARED', true);
}

require_once __DIR__ . '/../init.php';
