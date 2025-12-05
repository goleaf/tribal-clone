<?php
/**
 * Test initialization file
 * This file sets up a test database before loading the main init.php
 */

// Define test database path before loading config
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../data/test_tribal_wars.sqlite');
}

// Now load the main init
require_once __DIR__ . '/bootstrap.php';
