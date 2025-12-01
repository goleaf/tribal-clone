<?php
declare(strict_types=1);

class AuthController extends Controller
{
    public function login(): void
    {
        // Redirect to existing login form to keep current flow intact.
        header('Location: /auth/login.php');
        exit();
    }

    public function logout(): void
    {
        AuthMiddleware::requireAuth();
        session_destroy();
        header('Location: /auth/login.php');
        exit();
    }
}
