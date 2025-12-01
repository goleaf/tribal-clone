<?php
declare(strict_types=1);

/**
 * Simple environment probe for PHP 8.4+ readiness.
 * Run with: php tests/php_84_compat_check.php
 */

function checkExtensions(array $extensions): array
{
    $results = [];
    foreach ($extensions as $ext) {
        $results[$ext] = extension_loaded($ext);
    }
    return $results;
}

$minimum = 80400; // PHP 8.4.0
$current = PHP_VERSION_ID;

$deprecatedIni = [];
foreach (['session.sid_length', 'session.sid_bits_per_character'] as $iniKey) {
    $deprecatedIni[$iniKey] = ini_get($iniKey);
}

$report = [
    'php_version' => PHP_VERSION,
    'php_version_id' => $current,
    'meets_minimum' => $current >= $minimum,
    'extensions' => checkExtensions([
        'pdo',
        'pdo_sqlite',
        'sqlite3',
        'mysqli',
        'mbstring',
        'json',
        'curl',
        'ctype',
        'filter',
        'openssl',
    ]),
    'ini' => [
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => ini_get('error_reporting'),
        'memory_limit' => ini_get('memory_limit'),
        'deprecated_session_ini' => $deprecatedIni,
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!$report['meets_minimum']) {
    exit(1);
}
