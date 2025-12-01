<?php
declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/DeltaCalculator.php';

/**
 * Test suite for DeltaCalculator
 * 
 * Tests cursor generation, delta calculation, and delta application
 */
class DeltaCalculatorTest
{
    private Database $db;
    private DeltaCalculator $calculator;
    private int $testWorldId = 999;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->calculator = new DeltaCalculator($this->db);
    }
    
    public function runTests(): void
    {
        echo "Running DeltaCalculator Tests...\n\n";
        
        $this->setupTestData();
        
        $this->testCursorGeneration();
        $this->testCursorDecoding();
        $this->testApplyDeltaWithAdditions();
        $this->testApplyDeltaWithModifications();
        $this->testApplyDeltaWithRemovals();
        $this->testApplyDeltaIdempotence();
        $this->testDeltaCalculation();
        
        $this->cleanupTestData();
        
        echo "\n✓ All DeltaCalculator tests passed!\n";
    }
    
    private function setupTestData(): void
    {
        // Create test world if needed
        try {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO worlds (id, name, speed) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isi", $this->testWorldId, $name, $speed);
                $name = 'Test World';
                $speed = 1;
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // World might already exist
        }
        
        // Ensure cache_versions table exists
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS cache_versions (
                    world_id INTEGER PRIMARY KEY,
                    data_version INTEGER NOT NULL,
                    diplomacy_version INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                )"
            );
        } catch (Exception $e) {
            // Table might already exist
        }
    }
    
    private function cleanupTestData(): void
    {
        // Clean up test data
        try {
            $stmt = $this->db->prepare("DELETE FROM cache_versions WHERE world_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $this->testWorldId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
    
    private function testCursorGeneration(): void
    {
        echo "Test: Cursor Generation\n";
        
        $cursor = $this->calculator->generateCursor($this->testWorldId);
        
        assert(!empty($cursor), "Cursor should not be empty");
        assert(base64_decode($cursor, true) !== false, "Cursor should be valid base64");
        
        $decoded = json_decode(base64_decode($cursor), true);
        assert(isset($decoded['timestamp']), "Cursor should contain timestamp");
        assert(isset($decoded['version']), "Cursor should contain version");
        assert(isset($decoded['checksum']), "Cursor should contain checksum");
        assert($decoded['world_id'] === $this->testWorldId, "Cursor should contain world_id");
        
        echo "  ✓ Cursor generated successfully with all required fields\n\n";
    }
    
    private function testCursorDecoding(): void
    {
        echo "Test: Cursor Decoding and Validation\n";
        
        // Test valid cursor
        $cursor = $this->calculator->generateCursor($this->testWorldId);
        $decoded = base64_decode($cursor);
        $data = json_decode($decoded, true);
        
        assert(is_array($data), "Decoded cursor should be an array");
        assert($data['timestamp'] <= time(), "Cursor timestamp should be valid");
        
        // Test invalid cursor (should throw exception when used)
        $invalidCursor = base64_encode("invalid json");
        $exceptionThrown = false;
        
        try {
            $this->calculator->calculateDelta($invalidCursor, ['villages' => [], 'commands' => [], 'markers' => []], $this->testWorldId);
        } catch (InvalidArgumentException $e) {
            $exceptionThrown = true;
        }
        
        assert($exceptionThrown, "Invalid cursor should throw InvalidArgumentException");
        
        echo "  ✓ Cursor validation works correctly\n\n";
    }
    
    private function testApplyDeltaWithAdditions(): void
    {
        echo "Test: Apply Delta - Additions\n";
        
        $baseState = [
            'villages' => [
                ['id' => 1, 'name' => 'Village 1', 'coords' => ['x' => 500, 'y' => 500]]
            ],
            'commands' => [],
            'markers' => []
        ];
        
        $delta = [
            'added' => [
                'villages' => [
                    ['id' => 2, 'name' => 'Village 2', 'coords' => ['x' => 501, 'y' => 501]]
                ],
                'commands' => [
                    ['id' => 1, 'type' => 'attack', 'sourceVillageId' => 1, 'targetVillageId' => 2]
                ],
                'markers' => []
            ],
            'modified' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'removed' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ]
        ];
        
        $newState = $this->calculator->applyDelta($baseState, $delta);
        
        assert(count($newState['villages']) === 2, "Should have 2 villages after addition");
        assert(count($newState['commands']) === 1, "Should have 1 command after addition");
        assert($newState['villages'][1]['id'] === 2, "New village should be added");
        
        echo "  ✓ Delta additions applied correctly\n\n";
    }
    
    private function testApplyDeltaWithModifications(): void
    {
        echo "Test: Apply Delta - Modifications\n";
        
        $baseState = [
            'villages' => [
                ['id' => 1, 'name' => 'Village 1', 'points' => 100]
            ],
            'commands' => [],
            'markers' => []
        ];
        
        $delta = [
            'added' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'modified' => [
                'villages' => [
                    ['id' => 1, 'name' => 'Village 1 Modified', 'points' => 200]
                ],
                'commands' => [],
                'markers' => []
            ],
            'removed' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ]
        ];
        
        $newState = $this->calculator->applyDelta($baseState, $delta);
        
        assert(count($newState['villages']) === 1, "Should still have 1 village");
        assert($newState['villages'][0]['name'] === 'Village 1 Modified', "Village name should be modified");
        assert($newState['villages'][0]['points'] === 200, "Village points should be modified");
        
        echo "  ✓ Delta modifications applied correctly\n\n";
    }
    
    private function testApplyDeltaWithRemovals(): void
    {
        echo "Test: Apply Delta - Removals\n";
        
        $baseState = [
            'villages' => [
                ['id' => 1, 'name' => 'Village 1'],
                ['id' => 2, 'name' => 'Village 2']
            ],
            'commands' => [
                ['id' => 1, 'type' => 'attack'],
                ['id' => 2, 'type' => 'support']
            ],
            'markers' => []
        ];
        
        $delta = [
            'added' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'modified' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'removed' => [
                'villages' => [
                    ['id' => 2]
                ],
                'commands' => [
                    ['id' => 1]
                ],
                'markers' => []
            ]
        ];
        
        $newState = $this->calculator->applyDelta($baseState, $delta);
        
        assert(count($newState['villages']) === 1, "Should have 1 village after removal");
        assert(count($newState['commands']) === 1, "Should have 1 command after removal");
        assert($newState['villages'][0]['id'] === 1, "Correct village should remain");
        assert($newState['commands'][0]['id'] === 2, "Correct command should remain");
        
        echo "  ✓ Delta removals applied correctly\n\n";
    }
    
    private function testApplyDeltaIdempotence(): void
    {
        echo "Test: Apply Delta - Modifications Only (Idempotent)\n";
        
        $baseState = [
            'villages' => [
                ['id' => 1, 'name' => 'Village 1', 'points' => 100]
            ],
            'commands' => [],
            'markers' => []
        ];
        
        // Delta with only modifications (no additions) should be idempotent
        $delta = [
            'added' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ],
            'modified' => [
                'villages' => [
                    ['id' => 1, 'name' => 'Village 1 Modified', 'points' => 200]
                ],
                'commands' => [],
                'markers' => []
            ],
            'removed' => [
                'villages' => [],
                'commands' => [],
                'markers' => []
            ]
        ];
        
        // Apply delta once
        $newState1 = $this->calculator->applyDelta($baseState, $delta);
        
        // Apply same delta to the result
        $newState2 = $this->calculator->applyDelta($newState1, $delta);
        
        // For modifications only, results should be identical (idempotent)
        assert(count($newState1['villages']) === count($newState2['villages']), "Village count should be same");
        assert($newState1['villages'][0]['points'] === $newState2['villages'][0]['points'], "Modified village should be same");
        assert($newState2['villages'][0]['points'] === 200, "Points should remain modified");
        
        echo "  ✓ Delta modifications are idempotent\n\n";
    }
    
    private function testDeltaCalculation(): void
    {
        echo "Test: Delta Calculation\n";
        
        // Generate a cursor for current time
        $cursor = $this->calculator->generateCursor($this->testWorldId);
        
        // Current state (empty for test)
        $currentState = [
            'villages' => [],
            'commands' => [],
            'markers' => []
        ];
        
        // Calculate delta
        $result = $this->calculator->calculateDelta($cursor, $currentState, $this->testWorldId);
        
        assert(isset($result['delta']), "Result should contain delta");
        assert(isset($result['cursor']), "Result should contain next cursor");
        assert(isset($result['has_more']), "Result should contain has_more flag");
        
        assert(isset($result['delta']['added']), "Delta should contain added");
        assert(isset($result['delta']['modified']), "Delta should contain modified");
        assert(isset($result['delta']['removed']), "Delta should contain removed");
        
        assert(is_array($result['delta']['added']['villages']), "Added villages should be array");
        assert(is_array($result['delta']['added']['commands']), "Added commands should be array");
        assert(is_array($result['delta']['added']['markers']), "Added markers should be array");
        
        echo "  ✓ Delta calculation returns correct structure\n\n";
    }
}

// Run tests
try {
    $test = new DeltaCalculatorTest();
    $test->runTests();
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
