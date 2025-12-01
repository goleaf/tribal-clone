<?php

/**
 * ErrorHandler class - centralizes error and exception handling.
 */
class ErrorHandler
{
    /**
     * Bootstraps PHP error, exception, and shutdown handlers.
     */
    public static function initialize(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }

    /**
     * Handles PHP errors.
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Ignore errors disabled via PHP configuration
        if (!(error_reporting() & $errno)) {
            return false;
        }

        self::logError('ERROR', $errstr, $errfile, $errline);

        // Show detailed message when debug mode is enabled
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div class='error-message'>Error: $errstr in $errfile on line $errline</div>";
        } else {
            // Otherwise display a friendly notice for fatal user errors
            if ($errno === E_USER_ERROR) {
                self::displayUserFriendlyError('An application error occurred. The administrators have been notified.');
            }
        }

        // Prevent PHP from handling the error further
        return true;
    }

    /**
     * Handles uncaught PHP exceptions.
     */
    public static function handleException(\Throwable $exception): void
    {
        self::logError(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div class='error-message'>
                <h3>Exception: " . get_class($exception) . "</h3>
                <p>{$exception->getMessage()}</p>
                <p>File: {$exception->getFile()} line {$exception->getLine()}</p>
                <pre>{$exception->getTraceAsString()}</pre>
            </div>";
        } else {
            self::displayUserFriendlyError('An application error occurred. The administrators have been notified.');
        }
    }

    /**
     * Handles fatal errors detected on shutdown.
     */
    public static function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::logError(
                'FATAL',
                $error['message'],
                $error['file'],
                $error['line']
            );

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                echo "<div class='error-message'>
                    <h3>Fatal error</h3>
                    <p>{$error['message']}</p>
                    <p>File: {$error['file']} line {$error['line']}</p>
                </div>";
            } else {
                self::displayUserFriendlyError('A critical error occurred. The administrators have been notified.');
            }
        }
    }

    /**
     * Logs errors to a file.
     */
    private static function logError(string $type, string $message, string $file, int $line, string $trace = ''): void
    {
        $logFile = 'logs/errors.log';

        // Create the logs directory if it does not exist
        if (!file_exists('logs')) {
            mkdir('logs', 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $type: $message in $file line $line";

        if ($trace !== '') {
            $logEntry .= "\nTrace: $trace";
        }

        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Optional: send notification for severe errors
        if ($type === 'FATAL' || $type === 'EXCEPTION') {
            // TODO: Send email notifications to administrators
            // self::notifyAdmin($type, $message, $file, $line);
        }
    }

    /**
     * Displays a friendly error page to the user.
     */
    private static function displayUserFriendlyError(string $message): void
    {
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => $message]);
                exit;
            }
        }

        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Application error</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; }
                .error-container { max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #e3e3e3; border-radius: 5px; background: #f9f9f9; }
                h1 { color: #d9534f; }
                .back-link { margin-top: 20px; }
                .back-link a { color: #0275d8; text-decoration: none; }
                .back-link a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>Application error</h1>
                <p>$message</p>
                <div class='back-link'>
                    <a href='javascript:history.back()'>Return to the previous page</a> or <a href='index.php'>go to the home page</a>
                </div>
            </div>
        </body>
        </html>";

        exit;
    }
}
