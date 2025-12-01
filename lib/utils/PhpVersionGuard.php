<?php
declare(strict_types=1);

/**
 * Lightweight runtime guard to ensure PHP 8.4+.
 * Usage: include and call PhpVersionGuard::assertCompatible();
 */
final class PhpVersionGuard
{
    private const MIN_VERSION_ID = 80400; // PHP 8.4.0
    private const MIN_VERSION_STRING = '8.4.0';

    public static function assertCompatible(): void
    {
        if (PHP_VERSION_ID < self::MIN_VERSION_ID) {
            $message = sprintf(
                'PHP %s+ required. Current: %s',
                self::MIN_VERSION_STRING,
                PHP_VERSION
            );
            error_log($message);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo $message;
            exit;
        }
    }

    public static function isCompatible(): bool
    {
        return PHP_VERSION_ID >= self::MIN_VERSION_ID;
    }
}
