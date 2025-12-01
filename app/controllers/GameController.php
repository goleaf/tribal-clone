<?php
declare(strict_types=1);

class GameController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        // Reuse the existing game page to avoid duplicating logic during migration.
        require __DIR__ . '/../../game/game.php';
    }
}
