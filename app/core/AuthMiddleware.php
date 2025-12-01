<?php
declare(strict_types=1);

class AuthMiddleware
{
    public static function requireAuth(string $redirectTo = '/auth/login.php'): void
    {
        if (!isset($_SESSION['user_id'])) {
            $redirectParam = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $redirectTo . '?redirect=' . $redirectParam);
            exit();
        }
    }
}
