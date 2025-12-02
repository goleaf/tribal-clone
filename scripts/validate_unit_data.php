<?php
/**
 * Unit Data Validation Script
 * 
 * Validates units.json for:
 * - Required field presence and positive values
 * - RPS relationship consistency (pike def_cav > pike def_inf, etc.)
 * - Balance constraints
 * 
 * Requirements: 13.2, 13.3, 13.5
 */

require_once __DIR__ . '/../init.php';

class UnitDataValidator {
    private $errors = [];
    private $warnings = [];
    private $units = [];
    
    // Required fields for all units
    private const REQUIRED_FIELDS = [
        'name',
        'internal_name',
        'category',
        'building_type',
        'required_building_level',
        'cost',
        'population',
        'attack',
        'defense',
        'speed_min_per_field',
        'carry_capacity',
        'training_time_base',
        'rps_bonuses',
        'special_abilities',
        'description'
    ];
    
    // Required cost fields
    private const REQUIRED_COST_FIELDS = ['wood', 'clay', 'iron'];
    
    // Required defense fields
    private const REQUIRED_DEFENSE_FIELDS = ['infantry', 'cavalry', 'ranged'];
    
    // Valid categories
    private const VALID_CATEGORIES = [
        'infantry', 'cavalry', 'ranged', 'siege', 'scout', 'support', 'conquest'
    ];
    
    public function __construct(string $unitsFilePath) {
        if (!file_exists($unitsFilePath)) {
            throw new Exception("Units file not found: $unitsFilePath");
        }
        
        $content = file_get_contents($unitsFilePath);
        $this->units = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }
    }
    
    /**
     * Run all validations
     */
    public function validate(): bool {
        echo "=== Unit Data Validation ===\n\n";
        
        foreach ($this->units as $key => $unit) {
            // Skip metadata fields
            if (strpos($key, '_') === 0) {
                continue;
            }
            
            $this->validateUnit($key, $unit);
        }
        
        $this->validateRPSRelationships();
        $this->validateBalanceConstraints();
        
        return $this->reportResults();
    }
    
    /**
     * Validate a single unit
     */
    private function validateUnit(string $key, $unit): void {
        if (!is_array($unit)) {
            $this->addError($key, "Unit data must be an array");
            return;
        }
        
        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($unit[$field])) {
                $this->addError($key, "Missing required field: $field");
            }
        }
        
        // Validate internal_name matches key
        if (isset($unit['internal_name']) && $unit['internal_name'] !== $key) {
            $this->addError($key, "internal_name '{$unit['internal_name']}' does not match key '$key'");
        }
        
        // Validate category
        if (isset($unit['category']) && !in_array($unit['category'], self::VALID_CATEGORIES)) {
            $this->addError($key, "Invalid category: {$unit['category']}");
        }
        
        // Validate numeric fields are positive
        $this->validatePositiveNumber($key, $unit, 'population');
        $this->validatePositiveNumber($key, $unit, 'attack', true); // Allow 0 for scouts
        $this->validatePositiveNumber($key, $unit, 'speed_min_per_field');
        $this->validatePositiveNumber($key, $unit, 'carry_capacity', true); // Allow 0 for siege/scouts
        $this->validatePositiveNumber($key, $unit, 'training_time_base');
        $this->validatePositiveNumber($key, $unit, 'required_building_level');
        
        // Validate cost structure
        if (isset($unit['cost'])) {
            if (!is_array($unit['cost'])) {
                $this->addError($key, "cost must be an array");
            } else {
                foreach (self::REQUIRED_COST_FIELDS as $resource) {
                    if (!isset($unit['cost'][$resource])) {
                        $this->addError($key, "Missing cost field: $resource");
                    } elseif (!is_numeric($unit['cost'][$resource]) || $unit['cost'][$resource] < 0) {
                        $this->addError($key, "cost.$resource must be a non-negative number");
                    }
                }
            }
        }
        
        // Validate defense structure
        if (isset($unit['defense'])) {
            if (!is_array($unit['defense'])) {
                $this->addError($key, "defense must be an array");
            } else {
                foreach (self::REQUIRED_DEFENSE_FIELDS as $defType) {
                    if (!isset($unit['defense'][$defType])) {
                        $this->addError($key, "Missing defense field: $defType");
                    } elseif (!is_numeric($unit['defense'][$defType]) || $unit['defense'][$defType] < 0) {
                        $this->addError($key, "defense.$defType must be a non-negative number");
                    }
                }
            }
        }
        
        // Validate RPS bonuses structure
        if (isset($unit['rps_bonuses'])) {
            if (!is_array($unit['rps_bonuses'])) {
                $this->addError($key, "rps_bonuses must be an array");
            } else {
                foreach ($unit['rps_bonuses'] as $bonusKey => $bonusValue) {
                    if (!is_numeric($bonusValue) || $bonusValue < 1.0) {
                        $this->addError($key, "rps_bonuses.$bonusKey must be >= 1.0 (multiplier)");
                    }
                }
            }
        }
        
        // Validate special_abilities is array
        if (isset($unit['special_abilities']) && !is_array($unit['special_abilities'])) {
            $this->addError($key, "special_abilities must be an array");
        }
        
        // Validate aura_config if present
        if (isset($unit['aura_config'])) {
            if (!is_array($unit['aura_config'])) {
                $this->addError($key, "aura_config must be an array");
            } else {
                if (!isset($unit['aura_config']['def_multiplier'])) {
                    $this->addError($key, "aura_config missing def_multiplier");
                } elseif ($unit['aura_config']['def_multiplier'] < 1.0) {
                    $this->addError($key, "aura_config.def_multiplier must be >= 1.0");
                }
            }
        }
    }
    
    /**
     * Validate a numeric field is positive
     */
    private function validatePositiveNumber(string $unitKey, array $unit, string $field, bool $allowZero = false): void {
        if (!isset($unit[$field])) {
            return; // Already caught by required fields check
        }
        
        if (!is_numeric($unit[$field])) {
            $this->addError($unitKey, "$field must be a number");
            return;
        }
        
        $min = $allowZero ? 0 : 1;
        if ($unit[$field] < $min) {
            $this->addError($unitKey, "$field must be >= $min");
        }
    }
    
    /**
     * Validate RPS relationships
     * Requirements: 13.2, 13.5
     */
    private function validateRPSRelationships(): void {
        echo "\n--- Validating RPS Relationships ---\n";
        
        foreach ($this->units as $key => $unit) {
            if (strpos($key, '_') === 0) continue;
            
            if (!isset($unit['defense']) || !isset($unit['rps_bonuses'])) {
                continue;
            }
            
            $defense = $unit['defense'];
            $bonuses = $unit['rps_bonuses'];
            
            // Pikeneer: def_cavalry should be significantly lower than def_infantry
            // (because pike bonus is applied, base defense vs cavalry should be lower)
            if ($key === 'pikeneer' && isset($bonuses['vs_cavalry'])) {
                if ($defense['cavalry'] >= $defense['infantry']) {
                    $this->addWarning($key, 
                        "Pike unit has def_cavalry ({$defense['cavalry']}) >= def_infantry ({$defense['infantry']}). " .
                        "Expected lower base cavalry defense since RPS bonus is applied."
                    );
                }
            }
            
            // Ranged units with wall bonus: should have lower base defense
            if (isset($bonuses['wall_bonus_vs_infantry'])) {
                if ($defense['infantry'] > $defense['cavalry']) {
                    $this->addWarning($key,
                        "Ranged unit with wall bonus has higher def_infantry ({$defense['infantry']}) than def_cavalry ({$defense['cavalry']}). " .
                        "This may be intentional but verify balance."
                    );
                }
            }
            
            // Cavalry with vs_ranged bonus: verify it makes sense
            if (isset($bonuses['vs_ranged_open_field'])) {
                if ($unit['category'] !== 'cavalry') {
                    $this->addWarning($key,
                        "Non-cavalry unit has vs_ranged_open_field bonus. Verify this is intentional."
                    );
                }
            }
            
            // Anti-siege bonus: should only be on ranged units
            if (isset($bonuses['vs_siege'])) {
                if ($unit['category'] !== 'ranged') {
                    $this->addWarning($key,
                        "Non-ranged unit has vs_siege bonus. Verify this is intentional."
                    );
                }
            }
        }
    }
    
    /**
     * Validate balance constraints
     * Requirements: 13.3, 13.5
     */
    private function validateBalanceConstraints(): void {
        echo "\n--- Validating Balance Constraints ---\n";
        
        foreach ($this->units as $key => $unit) {
            if (strpos($key, '_') === 0) continue;
            
            if (!isset($unit['defense']) || !isset($unit['attack'])) {
                continue;
            }
            
            // Check for "balanced" units (like Shieldbearer)
            // Balanced units should have defense values within 20% of mean
            if ($key === 'shieldbearer' || (isset($unit['description']) && 
                strpos(strtolower($unit['description']), 'balanced') !== false)) {
                
                $defValues = array_values($unit['defense']);
                $mean = array_sum($defValues) / count($defValues);
                $maxVariance = $mean * 0.20;
                
                foreach ($defValues as $defValue) {
                    if (abs($defValue - $mean) > $maxVariance) {
                        $this->addWarning($key,
                            "Balanced unit has defense value ($defValue) more than 20% from mean ($mean). " .
                            "Variance: " . abs($defValue - $mean)
                        );
                        break;
                    }
                }
            }
            
            // Elite units should have significantly higher stats
            if (isset($unit['special_abilities']) && in_array('elite', $unit['special_abilities'])) {
                $totalDefense = array_sum(array_values($unit['defense']));
                if ($totalDefense < 200) {
                    $this->addWarning($key,
                        "Elite unit has relatively low total defense ($totalDefense). Expected > 200."
                    );
                }
                
                if ($unit['population'] < 2) {
                    $this->addWarning($key,
                        "Elite unit has low population cost ({$unit['population']}). Expected >= 2."
                    );
                }
            }
            
            // Scout units should have minimal combat stats
            if ($unit['category'] === 'scout') {
                if ($unit['attack'] > 10) {
                    $this->addWarning($key,
                        "Scout unit has high attack ({$unit['attack']}). Expected <= 10."
                    );
                }
                
                $totalDefense = array_sum(array_values($unit['defense']));
                if ($totalDefense > 30) {
                    $this->addWarning($key,
                        "Scout unit has high total defense ($totalDefense). Expected <= 30."
                    );
                }
            }
            
            // Siege units should be slow
            if ($unit['category'] === 'siege') {
                if ($unit['speed_min_per_field'] < 25) {
                    $this->addWarning($key,
                        "Siege unit is too fast ({$unit['speed_min_per_field']} min/field). Expected >= 25."
                    );
                }
            }
            
            // Cavalry should be fast
            if ($unit['category'] === 'cavalry') {
                if ($unit['speed_min_per_field'] > 15) {
                    $this->addWarning($key,
                        "Cavalry unit is too slow ({$unit['speed_min_per_field']} min/field). Expected <= 15."
                    );
                }
            }
            
            // Conquest units should be very expensive
            if ($unit['category'] === 'conquest') {
                $totalCost = ($unit['cost']['wood'] ?? 0) + 
                            ($unit['cost']['clay'] ?? 0) + 
                            ($unit['cost']['iron'] ?? 0);
                
                if ($totalCost < 100000) {
                    $this->addWarning($key,
                        "Conquest unit has low total cost ($totalCost). Expected >= 100000."
                    );
                }
                
                if ($unit['population'] < 50) {
                    $this->addWarning($key,
                        "Conquest unit has low population cost ({$unit['population']}). Expected >= 50."
                    );
                }
            }
        }
    }
    
    /**
     * Add an error
     */
    private function addError(string $unit, string $message): void {
        $this->errors[] = "[$unit] ERROR: $message";
    }
    
    /**
     * Add a warning
     */
    private function addWarning(string $unit, string $message): void {
        $this->warnings[] = "[$unit] WARNING: $message";
    }
    
    /**
     * Report validation results
     */
    private function reportResults(): bool {
        echo "\n=== Validation Results ===\n\n";
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo "✓ All validations passed! No errors or warnings.\n";
            return true;
        }
        
        if (!empty($this->errors)) {
            echo "ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "  $error\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "  $warning\n";
            }
            echo "\n";
        }
        
        $hasErrors = !empty($this->errors);
        echo $hasErrors ? "✗ Validation FAILED\n" : "✓ Validation PASSED (with warnings)\n";
        
        return !$hasErrors;
    }
}

// Run validation
try {
    $unitsFile = __DIR__ . '/../data/units.json';
    $validator = new UnitDataValidator($unitsFile);
    $success = $validator->validate();
    
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
