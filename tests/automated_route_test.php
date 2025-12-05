<?php
declare(strict_types=1);

/**
 * Automated Route Testing with HTTP Requests
 * Tests all routes by making actual HTTP requests
 * 
 * Run: php tests/automated_route_test.php
 */

class AutomatedRouteTest {
    private string $baseUrl = 'http://localhost:8000';
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    private array $publicRoutes = [
        'auth/login.php',
        'auth/register.php',
        'help.php',
        'guides.php',
        'terms.php',
        'game/world_select.php',
    ];
    
    private array $authenticatedRoutes = [
        'game/game.php',
        'game/game_wap.php',
        'game/intel.php',
        'map/map.php',
        'player/player.php',
        'combat/attack.php',
        'research/get_research_panel.php',
        'units/get_recruitment_queue.php',
    ];
    
    public function run(): void {
        echo "=== AUTOMATED ROUTE TEST SUITE ===\n\n";
        
        // Check if server is running
        if (!$this->checkServer()) {
            echo "❌ Server is not running on {$this->baseUrl}\n";
            echo "Please start the server with: php -S localhost:8000\n";
            exit(1);
        }
        
        echo "✓ Server is running\n\n";
        
        $this->testPublicRoutes();
        $this->testAuthenticatedRoutes();
        $this->testImageLoading();
        $this->generateReport();
    }
    
    private function checkServer(): bool {
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode > 0;
    }
    
    private function testPublicRoutes(): void {
        echo "Testing Public Routes...\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($this->publicRoutes as $route) {
            $url = "{$this->baseUrl}/{$route}";
            $result = $this->makeRequest($url);
            
            if ($result['success']) {
                $this->pass("✓ {$route} - HTTP {$result['code']}");
            } else {
                $this->fail("✗ {$route} - {$result['error']}");
            }
        }
        
        echo "\n";
    }
    
    private function testAuthenticatedRoutes(): void {
        echo "Testing Authenticated Routes (should redirect or show login)...\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($this->authenticatedRoutes as $route) {
            $url = "{$this->baseUrl}/{$route}";
            $result = $this->makeRequest($url);
            
            // Authenticated routes should either return 200 (if session exists) or 302 (redirect)
            if ($result['success'] || $result['code'] === 302) {
                $this->pass("✓ {$route} - HTTP {$result['code']}");
            } else {
                $this->fail("✗ {$route} - {$result['error']}");
            }
        }
        
        echo "\n";
    }
    
    private function testImageLoading(): void {
        echo "Testing Critical Image Loading...\n";
        echo str_repeat("-", 60) . "\n";
        
        $criticalImages = [
            'img/ds_graphic/wood.png',
            'img/ds_graphic/stone.png',
            'img/ds_graphic/iron.png',
            'img/main_building.png',
            'img/barracks.png',
            'img/tw_map/map_center.png',
            'favicon.ico',
        ];
        
        foreach ($criticalImages as $image) {
            $url = "{$this->baseUrl}/{$image}";
            $result = $this->makeRequest($url);
            
            if ($result['success'] && $result['size'] > 0) {
                $this->pass("✓ {$image} - {$result['size']} bytes");
            } else {
                $this->fail("✗ {$image} - Failed to load");
            }
        }
        
        echo "\n";
    }
    
    private function makeRequest(string $url): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 400,
            'code' => $httpCode,
            'size' => $contentLength,
            'error' => $error ?: "HTTP {$httpCode}",
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
        
        if ($this->passed + $this->failed > 0) {
            echo "Success Rate: " . round(($this->passed / ($this->passed + $this->failed)) * 100, 2) . "%\n";
        }
        
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
$test = new AutomatedRouteTest();
$test->run();
