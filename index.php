<?php
require 'init.php';
require_once __DIR__ . '/app/core/Router.php';
require_once __DIR__ . '/app/core/Controller.php';
require_once __DIR__ . '/app/core/AuthMiddleware.php';
require_once __DIR__ . '/app/controllers/HomeController.php';
require_once __DIR__ . '/app/controllers/GameController.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

$router = new Router($conn);

// Public homepage
$router->get('/', [HomeController::class, 'index']);

// Auth routes
$router->get('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth' => true]);

// Authenticated gameplay entry
$router->get('/game', [GameController::class, 'index'], ['auth' => true]);

$router->dispatch();
