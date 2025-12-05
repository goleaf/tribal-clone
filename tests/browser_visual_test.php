<?php
declare(strict_types=1);
/**
 * Visual Browser Test Page
 * Open this in a browser to visually test all routes and images
 * 
 * Access: http://localhost/tests/browser_visual_test.php
 */

require_once __DIR__ . '/../init.php';

// Check if user is logged in for authenticated tests
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browser Visual Test Suite</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .status {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }
        .status.logged-in {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.logged-out {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .section {
            margin: 30px 0;
        }
        .section h2 {
            color: #555;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .test-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fafafa;
        }
        .test-item.success {
            border-color: #28a745;
            background: #f0fff4;
        }
        .test-item.error {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .test-item h3 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #333;
        }
        .test-item a {
            display: inline-block;
            padding: 6px 12px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            margin-top: 8px;
        }
        .test-item a:hover {
            background: #0056b3;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .image-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            background: white;
        }
        .image-item img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 8px;
        }
        .image-item.loaded {
            border-color: #28a745;
        }
        .image-item.failed {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .image-name {
            font-size: 11px;
            color: #666;
            word-break: break-all;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .stat {
            flex: 1;
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .run-all-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin: 20px 0;
        }
        .run-all-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Browser Visual Test Suite</h1>
        
        <?php if ($isLoggedIn): ?>
            <div class="status logged-in">
                ✓ Logged in as User ID: <?= $_SESSION['user_id'] ?> - All tests available
            </div>
        <?php else: ?>
            <div class="status logged-out">
                ⚠ Not logged in - Some tests require authentication. <a href="../auth/login.php">Login here</a>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-value" id="total-tests">0</div>
                <div class="stat-label">Total Tests</div>
            </div>
            <div class="stat">
                <div class="stat-value" id="passed-tests" style="color: #28a745;">0</div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat">
                <div class="stat-value" id="failed-tests" style="color: #dc3545;">0</div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
        
        <a href="#" class="run-all-btn" onclick="runAllTests(); return false;">▶ Run All Tests</a>
        
        <!-- Image Tests -->
        <div class="section">
            <h2>📷 Image Loading Tests</h2>
            <div class="image-grid" id="image-tests">
                <?php
                $images = [
                    'Resource Icons' => [
                        '../img/ds_graphic/wood.png',
                        '../img/ds_graphic/stone.png',
                        '../img/ds_graphic/iron.png',
                        '../img/ds_graphic/resources/population.png',
                    ],
                    'Building Icons' => [
                        '../img/main_building.png',
                        '../img/barracks.png',
                        '../img/stable.png',
                        '../img/garage.png',
                        '../img/smithy.png',
                        '../img/market.png',
                        '../img/warehouse.png',
                        '../img/farm.png',
                        '../img/wall.png',
                    ],
                    'Map Icons' => [
                        '../img/tw_map/map_n.png',
                        '../img/tw_map/map_s.png',
                        '../img/tw_map/map_e.png',
                        '../img/tw_map/map_w.png',
                        '../img/tw_map/map_center.png',
                        '../img/tw_map/map_v6.png',
                        '../img/tw_map/map_v4.png',
                        '../img/tw_map/map_v2.png',
                    ],
                ];
                
                foreach ($images as $category => $imageList) {
                    echo "<h3 style='grid-column: 1/-1; margin: 15px 0 5px; color: #666;'>$category</h3>";
                    foreach ($imageList as $img) {
                        $name = basename($img);
                        echo "<div class='image-item' data-src='$img'>";
                        echo "<img src='$img' alt='$name' onerror='imageLoadError(this)' onload='imageLoadSuccess(this)'>";
                        echo "<div class='image-name'>$name</div>";
                        echo "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- Route Tests -->
        <div class="section">
            <h2>🔗 Route Accessibility Tests</h2>
            <div class="test-grid" id="route-tests">
                <?php
                $routes = [
                    'Authentication' => [
                        ['name' => 'Login Page', 'url' => '../auth/login.php', 'auth' => false],
                        ['name' => 'Register Page', 'url' => '../auth/register.php', 'auth' => false],
                    ],
                    'Game Pages' => [
                        ['name' => 'Village Overview', 'url' => '../game/village_overview.php', 'auth' => true],
                        ['name' => 'Buildings', 'url' => '../game/buildings.php', 'auth' => true],
                    ],
                    'Map' => [
                        ['name' => 'World Map', 'url' => '../map/map.php', 'auth' => true],
                    ],
                    'Player' => [
                        ['name' => 'Player Profile', 'url' => '../player/player.php', 'auth' => true],
                    ],
                    'Static Pages' => [
                        ['name' => 'Help', 'url' => '../help.php', 'auth' => false],
                        ['name' => 'Guides', 'url' => '../guides.php', 'auth' => false],
                        ['name' => 'Terms', 'url' => '../terms.php', 'auth' => false],
                    ],
                ];
                
                foreach ($routes as $category => $routeList) {
                    echo "<h3 style='grid-column: 1/-1; margin: 15px 0 5px; color: #666;'>$category</h3>";
                    foreach ($routeList as $route) {
                        $disabled = $route['auth'] && !$isLoggedIn ? 'disabled' : '';
                        $class = $route['auth'] && !$isLoggedIn ? 'test-item' : 'test-item';
                        echo "<div class='$class'>";
                        echo "<h3>{$route['name']}</h3>";
                        if ($route['auth'] && !$isLoggedIn) {
                            echo "<small style='color: #dc3545;'>Requires login</small>";
                        } else {
                            echo "<a href='{$route['url']}' target='_blank'>Open Page</a>";
                        }
                        echo "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- AJAX Tests -->
        <div class="section">
            <h2>⚡ AJAX Endpoint Tests</h2>
            <div class="test-grid" id="ajax-tests">
                <div class="test-item">
                    <h3>Get Resources</h3>
                    <button onclick="testAjax('../ajax/resources/get_resources.php', this)">Test Endpoint</button>
                    <div class="result"></div>
                </div>
                <div class="test-item">
                    <h3>AJAX Proxy</h3>
                    <button onclick="testAjax('../ajax_proxy.php', this)">Test Endpoint</button>
                    <div class="result"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let totalTests = 0;
        let passedTests = 0;
        let failedTests = 0;
        
        function updateStats() {
            document.getElementById('total-tests').textContent = totalTests;
            document.getElementById('passed-tests').textContent = passedTests;
            document.getElementById('failed-tests').textContent = failedTests;
        }
        
        function imageLoadSuccess(img) {
            const item = img.closest('.image-item');
            item.classList.add('loaded');
            totalTests++;
            passedTests++;
            updateStats();
        }
        
        function imageLoadError(img) {
            const item = img.closest('.image-item');
            item.classList.add('failed');
            img.alt = '❌ Failed to load';
            totalTests++;
            failedTests++;
            updateStats();
        }
        
        async function testAjax(url, button) {
            const item = button.closest('.test-item');
            const result = item.querySelector('.result');
            
            try {
                const response = await fetch(url);
                const text = await response.text();
                
                if (response.ok) {
                    item.classList.add('success');
                    result.innerHTML = '<small style="color: #28a745;">✓ Success</small>';
                    passedTests++;
                } else {
                    item.classList.add('error');
                    result.innerHTML = `<small style="color: #dc3545;">✗ Error: ${response.status}</small>`;
                    failedTests++;
                }
            } catch (error) {
                item.classList.add('error');
                result.innerHTML = `<small style="color: #dc3545;">✗ ${error.message}</small>`;
                failedTests++;
            }
            
            totalTests++;
            updateStats();
        }
        
        function runAllTests() {
            // Reset stats
            totalTests = 0;
            passedTests = 0;
            failedTests = 0;
            updateStats();
            
            // Reload all images
            document.querySelectorAll('.image-item img').forEach(img => {
                img.src = img.src + '?' + Date.now();
            });
            
            // Test all AJAX endpoints
            document.querySelectorAll('#ajax-tests button').forEach(btn => {
                btn.click();
            });
        }
        
        // Initialize on load
        window.addEventListener('load', () => {
            updateStats();
        });
    </script>
</body>
</html>
