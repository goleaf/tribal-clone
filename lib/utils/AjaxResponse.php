<?php
declare(strict_types=1);

/**
 * AjaxResponse class - helpers for AJAX responses
 */
class AjaxResponse 
{
    /**
     * Send a success JSON response
     *
     * @param mixed $data Payload to send
     * @param string $message Optional success message
     * @return void
     */
    public static function success(mixed $data = null, string $message = ''): void 
    {
        self::send([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Send an error JSON response
     *
     * @param string $message Error message
     * @param mixed $data Optional extra data
     * @param int $code Optional HTTP status code
     * @param string|null $errorCode Optional application error code (e.g., ERR_CAP)
     * @return void
     */
    public static function error(string $message, mixed $data = null, int $code = 400, ?string $errorCode = null): void 
    {
        // Set the HTTP status header
        http_response_code($code);
        
        $payload = [
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'code' => $code
        ];
        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        self::send($payload);
    }
    
    /**
     * Send a warning JSON response
     *
     * @param string $message Warning message
     * @param mixed $data Optional extra data
     * @return void
     */
    public static function warning(string $message, mixed $data = null): void 
    {
        self::send([
            'status' => 'warning',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Send an info JSON response
     *
     * @param string $message Informational message
     * @param mixed $data Optional extra data
     * @return void
     */
    public static function info(string $message, mixed $data = null): void 
    {
        self::send([
            'status' => 'info',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Send a JSON response
     *
     * @param array $data Payload to send
     * @return void
     */
    private static function send(array $data): void 
    {
        // Set JSON headers
        header('Content-Type: application/json; charset=utf-8');
        
        // Add no-cache headers
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Allow basic CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Add timestamp
        $data['timestamp'] = time();
        
        // Encode and output JSON
        echo json_encode($data);
        
        // Stop script execution
        exit;
    }
    
    /**
     * Check whether the current request is AJAX
     *
     * @return bool True when it is an AJAX request
     */
    public static function isAjaxRequest(): bool 
    {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }
    
    /**
     * Handle an exception and send an AJAX error response.
     * Use this inside try-catch blocks for AJAX requests.
     *
     * @param \Throwable $exception Exception instance
     * @param bool $logException Whether to log the exception (default true)
     * @return void
     */
    public static function handleException(\Throwable $exception, bool $logException = true): void 
    {
        // Optionally log the exception
        if ($logException && class_exists('ErrorHandler')) {
            ErrorHandler::handleException($exception);
        }
        
        // Send detailed info when in debug mode
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::error(
                $exception->getMessage(),
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ],
                500
            );
        } else {
            // In production, send only a generic message
            self::error('An error occurred while processing the request.', null, 500);
        }
    }
} 
