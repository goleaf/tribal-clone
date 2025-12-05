<?php
declare(strict_types=1);

/**
 * Comprehensive Browser Test Suite
 * Tests all routes and image loading across the application
 * 
 * Run: php tests/browser_comprehensive_test.php
 */

require_once __DIR__ . '/../init.php';

class BrowserComprehensiveTest {
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    // All public routes to test
    private array $routes = [
        // Auth routes
        'auth/login.php' => ['method' => 'GET', 'requires_auth' => false],
        'auth/register.php' => ['method' => 'GET', 'requires_auth' => false],
        
        // Main game routes
        'game/game.php' => ['method' => 'GET', 'requires_auth' => true],
        'game/game_wap.php' => ['method' => 'GET', 'requires_auth' => true],
        'game/intel.php' => ['method' => 'GET', 'requires_auth' => true],
        'game/world_select.php' => ['method' => 'GET', 'requires_auth' => false],
        
        // Building routes
        'buildings/upgrade_building.php' => ['method' => 'POST', 'requires_auth' => true],
        'buildings/cancel_upgrade.php' => ['method' => 'POST', 'requires_auth' => true],
        
        // Unit routes
        'units/recruit_units.php' => ['method' => 'POST', 'requires_auth' => true],
        'units/get_recruitment_queue.php' => ['method' => 'GET', 'requires_auth' => true],
        
        // Research routes
        'research/get_research_panel.php' => ['method' => 'GET', 'requires_auth' => true],
        
        // Combat routes
        'combat/attack.php' => ['method' => 'GET', 'requires_auth' => true],
        
        // Player routes
        'player/player.php' => ['method' => 'GET', 'requires_auth' => true],
        'player/rename_village.php' => ['method' => 'POST', 'requires_auth' => true],
        
        // Map routes
        'map/map.php' => ['method' => 'GET', 'requires_auth' => true],
        
        // AJAX routes
        'ajax/resources/get_resources.php' => ['method' => 'GET', 'requires_auth' => true],
        'ajax_proxy.php' => ['method' => 'GET', 'requires_auth' => true],
        
        // Static pages
        'help.php' => ['method' => 'GET', 'requires_auth' => false],
        'guides.php' => ['method' => 'GET', 'requires_auth' => false],
        'terms.php' => ['method' => 'GET', 'requires_auth' => false],
    ];
    
    // All images to verify
    private array $images = [
        // Resource icons
        'img/ds_graphic/wood.png',
        'img/ds_graphic/stone.png',
        'img/ds_graphic/iron.png',
        'img/ds_graphic/resources/population.png',
        
        // Building icons
        'img/main_building.png',
        'img/barracks.png',
        'img/stable.png',
        'img/garage.png',
        'img/smithy.png',
        'img/market.png',
        'img/warehouse.png',
        'img/farm.png',
        'img/wall.png',
        'img/clay_pit.png',
        'img/iron_mine.png',
        'img/sawmill.png',
        'img/storage.png',
        'img/church.png',
        
        // Map icons
        'img/tw_map/map_n.png',
        'img/tw_map/map_s.png',
        'img/tw_map/map_e.png',
        'img/tw_map/map_w.png',
        'img/tw_map/map_center.png',
        'img/tw_map/map_v6.png',
        'img/tw_map/map_v4.png',
        'img/tw_map/map_v2.png',
        'img/tw_map/map_free.png',
        'img/tw_map/reserved_player.png',
        'img/tw_map/reserved_team.png',
        'img/tw_map/incoming_attack.png',
        'img/tw_map/attack.png',
        'img/tw_map/return.png',
        'img/tw_map/village_notes.png',
        
        // Map terrain tiles
        'img/tw_map/gras1.png',
        'img/tw_map/gras2.png',
        'img/tw_map/gras3.png',
        'img/tw_map/gras4.png',
        'img/tw_map/berg1.png',
        'img/tw_map/berg2.png',
        'img/tw_map/berg3.png',
        'img/tw_map/berg4.png',
        
        // Report icons
        'img/reports/victory.svg',
        'img/reports/defeat.svg',
        'img/reports/scout.svg',
        'img/reports/win.jpg',
        
        // Other
        'img/notification.png',
        'img/population.png',
        'img/village_bg.jpg',
        'favicon.ico',
    ];
    
    public function run(): void {
        echo "=== COMPREHENSIVE BROWSER TEST SUITE ===\n\n";
        
        $this->testImageFiles();
        $this->testRouteAccessibility();
        $this->generateReport();
    }
    
    private function testImageFiles(): void {
        echo "Testing Image Files...\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($this->images as $imagePath) {
            $fullPath = __DIR__ . '/../' . $imagePath;
            
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                $readable = is_readable($fullPath);
                
                if ($readable && $size > 0) {
                    $this->pass("✓ $imagePath ({$size} bytes)");
                } else {
                    $this->fail("✗ $imagePath - File exists but is empty or unreadable");
                }
            } else {
                $this->fail("✗ $imagePath - File not found");
            }
        }
        
        echo "\n";
    }
    
    private function testRouteAccessibility(): void {
        echo "Testing Route Accessibility...\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($this->routes as $route => $config) {
            $fullPath = __DIR__ . '/../' . $route;
            
            if (file_exists($fullPath)) {
                // Check if file is valid PHP
                $syntax = $this->checkPHPSyntax($fullPath);
                
                if ($syntax['valid']) {
                    $this->pass("✓ $route - Syntax valid");
                } else {
                    $this->fail("✗ $route - Syntax error: {$syntax['error']}");
                }
            } else {
                $this->fail("✗ $route - File not found");
            }
        }
        
        echo "\n";
    }
    
    private function checkPHPSyntax(string $file): array {
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
        
        return [
            'valid' => $returnVar === 0,
            'error' => $returnVar !== 0 ? implode("\n", $output) : null
        ];
    }
    
    private function pass(string $message): void {
        $this->results[] = ['status' => 'pass', 'message' => $message];
        $this->passed++;
        echo $message . "\n";
    }
    
    private function fail(string $message): void {
        $this->results[] = ['status' => 'fail', 'message' => $message];
        $this->failed++;
        echo $message . "\n";
    }
    
    private function generateReport(): void {
        echo str_repeat("=", 60) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total Tests: " . ($this->passed + $this->failed) . "\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Success Rate: " . round(($this->passed / ($this->passed + $this->failed)) * 100, 2) . "%\n";
        
        if ($this->failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'fail') {
                    echo "  - {$result['message']}\n";
                }
            }
        }
        
        exit($this->failed > 0 ? 1 : 0);
    }
}

// Run the test suite
$test = new BrowserComprehensiveTest();
$test->run();
