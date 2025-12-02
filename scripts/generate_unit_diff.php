<?php
/**
 * Unit Data Diff Generator
 * 
 * Compares current units.json with previous version and generates
 * a human-readable diff showing stat changes for changelog documentation.
 * 
 * Requirements: 13.4
 */

require_once __DIR__ . '/../init.php';

class UnitDataDiffGenerator {
    private $oldUnits = [];
    private $newUnits = [];
    private $changes = [];
    
    public function __construct(string $oldFilePath, string $newFilePath) {
        if (!file_exists($oldFilePath)) {
            throw new Exception("Old units file not found: $oldFilePath");
        }
        if (!file_exists($newFilePath)) {
            throw new Exception("New units file not found: $newFilePath");
        }
        
        $oldContent = file_get_contents($oldFilePath);
        $this->oldUnits = json_decode($oldContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in old file: " . json_last_error_msg());
        }
        
        $newContent = file_get_contents($newFilePath);
        $this->newUnits = json_decode($newContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in new file: " . json_last_error_msg());
        }
    }
    
    /**
     * Generate diff
     */
    public function generate(): string {
        $output = "# Unit Data Changes\n\n";
        $output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Get version info if available
        $oldVersion = $this->oldUnits['_version'] ?? 'unknown';
        $newVersion = $this->newUnits['_version'] ?? 'unknown';
        $output .= "Version: $oldVersion → $newVersion\n\n";
        
        // Find added units
        $addedUnits = $this->findAddedUnits();
        if (!empty($addedUnits)) {
            $output .= "## New Units\n\n";
            foreach ($addedUnits as $unitKey) {
                $unit = $this->newUnits[$unitKey];
                $output .= $this->formatNewUnit($unitKey, $unit);
            }
            $output .= "\n";
        }
        
        // Find removed units
        $removedUnits = $this->findRemovedUnits();
        if (!empty($removedUnits)) {
            $output .= "## Removed Units\n\n";
            foreach ($removedUnits as $unitKey) {
                $unit = $this->oldUnits[$unitKey];
                $output .= "- **{$unit['name']}** (`$unitKey`)\n";
            }
            $output .= "\n";
        }
        
        // Find modified units
        $modifiedUnits = $this->findModifiedUnits();
        if (!empty($modifiedUnits)) {
            $output .= "## Modified Units\n\n";
            foreach ($modifiedUnits as $unitKey => $changes) {
                $output .= $this->formatUnitChanges($unitKey, $changes);
            }
        }
        
        // Summary
        $output .= "\n## Summary\n\n";
        $output .= "- New units: " . count($addedUnits) . "\n";
        $output .= "- Removed units: " . count($removedUnits) . "\n";
        $output .= "- Modified units: " . count($modifiedUnits) . "\n";
        
        if (empty($addedUnits) && empty($removedUnits) && empty($modifiedUnits)) {
            $output .= "\n**No changes detected.**\n";
        }
        
        return $output;
    }
    
    /**
     * Find units that were added
     */
    private function findAddedUnits(): array {
        $added = [];
        foreach ($this->newUnits as $key => $unit) {
            if (strpos($key, '_') === 0) continue; // Skip metadata
            if (!isset($this->oldUnits[$key])) {
                $added[] = $key;
            }
        }
        return $added;
    }
    
    /**
     * Find units that were removed
     */
    private function findRemovedUnits(): array {
        $removed = [];
        foreach ($this->oldUnits as $key => $unit) {
            if (strpos($key, '_') === 0) continue; // Skip metadata
            if (!isset($this->newUnits[$key])) {
                $removed[] = $key;
            }
        }
        return $removed;
    }
    
    /**
     * Find units that were modified
     */
    private function findModifiedUnits(): array {
        $modified = [];
        foreach ($this->newUnits as $key => $newUnit) {
            if (strpos($key, '_') === 0) continue; // Skip metadata
            if (!isset($this->oldUnits[$key])) continue; // Already in added
            
            $oldUnit = $this->oldUnits[$key];
            $changes = $this->compareUnits($oldUnit, $newUnit);
            
            if (!empty($changes)) {
                $modified[$key] = $changes;
            }
        }
        return $modified;
    }
    
    /**
     * Compare two unit definitions
     */
    private function compareUnits(array $old, array $new): array {
        $changes = [];
        
        // Compare simple fields
        $simpleFields = [
            'name', 'category', 'building_type', 'required_building_level',
            'required_tech', 'required_tech_level', 'population', 'attack',
            'speed_min_per_field', 'carry_capacity', 'training_time_base'
        ];
        
        foreach ($simpleFields as $field) {
            if (!isset($old[$field]) && !isset($new[$field])) continue;
            
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            
            if ($oldVal !== $newVal) {
                $changes[$field] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                    'type' => 'simple'
                ];
            }
        }
        
        // Compare cost
        if (isset($old['cost']) || isset($new['cost'])) {
            $costChanges = $this->compareArray($old['cost'] ?? [], $new['cost'] ?? []);
            if (!empty($costChanges)) {
                $changes['cost'] = [
                    'changes' => $costChanges,
                    'type' => 'nested'
                ];
            }
        }
        
        // Compare defense
        if (isset($old['defense']) || isset($new['defense'])) {
            $defenseChanges = $this->compareArray($old['defense'] ?? [], $new['defense'] ?? []);
            if (!empty($defenseChanges)) {
                $changes['defense'] = [
                    'changes' => $defenseChanges,
                    'type' => 'nested'
                ];
            }
        }
        
        // Compare RPS bonuses
        if (isset($old['rps_bonuses']) || isset($new['rps_bonuses'])) {
            $rpsChanges = $this->compareArray($old['rps_bonuses'] ?? [], $new['rps_bonuses'] ?? []);
            if (!empty($rpsChanges)) {
                $changes['rps_bonuses'] = [
                    'changes' => $rpsChanges,
                    'type' => 'nested'
                ];
            }
        }
        
        // Compare special abilities
        if (isset($old['special_abilities']) || isset($new['special_abilities'])) {
            $oldAbilities = $old['special_abilities'] ?? [];
            $newAbilities = $new['special_abilities'] ?? [];
            
            if (json_encode($oldAbilities) !== json_encode($newAbilities)) {
                $changes['special_abilities'] = [
                    'old' => $oldAbilities,
                    'new' => $newAbilities,
                    'type' => 'array'
                ];
            }
        }
        
        // Compare aura config
        if (isset($old['aura_config']) || isset($new['aura_config'])) {
            $auraChanges = $this->compareArray($old['aura_config'] ?? [], $new['aura_config'] ?? []);
            if (!empty($auraChanges)) {
                $changes['aura_config'] = [
                    'changes' => $auraChanges,
                    'type' => 'nested'
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Compare two arrays
     */
    private function compareArray(array $old, array $new): array {
        $changes = [];
        
        // Find all keys
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            
            if ($oldVal !== $newVal) {
                $changes[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Format a new unit for output
     */
    private function formatNewUnit(string $key, array $unit): string {
        $output = "### {$unit['name']} (`$key`)\n\n";
        $output .= "- **Category**: {$unit['category']}\n";
        $output .= "- **Building**: {$unit['building_type']} (level {$unit['required_building_level']})\n";
        
        if (!empty($unit['required_tech'])) {
            $output .= "- **Tech**: {$unit['required_tech']} (level {$unit['required_tech_level']})\n";
        }
        
        $output .= "- **Cost**: {$unit['cost']['wood']}w / {$unit['cost']['clay']}c / {$unit['cost']['iron']}i\n";
        $output .= "- **Population**: {$unit['population']}\n";
        $output .= "- **Attack**: {$unit['attack']}\n";
        $output .= "- **Defense**: {$unit['defense']['infantry']} / {$unit['defense']['cavalry']} / {$unit['defense']['ranged']}\n";
        $output .= "- **Speed**: {$unit['speed_min_per_field']} min/field\n";
        $output .= "- **Carry**: {$unit['carry_capacity']}\n";
        $output .= "- **Training Time**: " . $this->formatTime($unit['training_time_base']) . "\n";
        
        if (!empty($unit['rps_bonuses'])) {
            $output .= "- **RPS Bonuses**: " . json_encode($unit['rps_bonuses']) . "\n";
        }
        
        if (!empty($unit['special_abilities'])) {
            $output .= "- **Special Abilities**: " . implode(', ', $unit['special_abilities']) . "\n";
        }
        
        $output .= "\n";
        return $output;
    }
    
    /**
     * Format unit changes for output
     */
    private function formatUnitChanges(string $key, array $changes): string {
        $unit = $this->newUnits[$key];
        $output = "### {$unit['name']} (`$key`)\n\n";
        
        foreach ($changes as $field => $change) {
            if ($change['type'] === 'simple') {
                $output .= $this->formatSimpleChange($field, $change['old'], $change['new']);
            } elseif ($change['type'] === 'nested') {
                $output .= $this->formatNestedChange($field, $change['changes']);
            } elseif ($change['type'] === 'array') {
                $output .= $this->formatArrayChange($field, $change['old'], $change['new']);
            }
        }
        
        $output .= "\n";
        return $output;
    }
    
    /**
     * Format a simple field change
     */
    private function formatSimpleChange(string $field, $old, $new): string {
        $oldStr = $old === null ? 'null' : $old;
        $newStr = $new === null ? 'null' : $new;
        
        // Add percentage change for numeric fields
        $percentChange = '';
        if (is_numeric($old) && is_numeric($new) && $old > 0) {
            $percent = (($new - $old) / $old) * 100;
            $sign = $percent >= 0 ? '+' : '';
            $percentChange = " ({$sign}" . number_format($percent, 1) . "%)";
        }
        
        // Format time fields
        if ($field === 'training_time_base') {
            $oldStr = $this->formatTime($old);
            $newStr = $this->formatTime($new);
        }
        
        return "- **$field**: $oldStr → $newStr$percentChange\n";
    }
    
    /**
     * Format nested changes
     */
    private function formatNestedChange(string $field, array $changes): string {
        $output = "- **$field**:\n";
        foreach ($changes as $subField => $change) {
            $oldStr = $change['old'] === null ? 'null' : $change['old'];
            $newStr = $change['new'] === null ? 'null' : $change['new'];
            
            // Add percentage change for numeric fields
            $percentChange = '';
            if (is_numeric($change['old']) && is_numeric($change['new']) && $change['old'] > 0) {
                $percent = (($change['new'] - $change['old']) / $change['old']) * 100;
                $sign = $percent >= 0 ? '+' : '';
                $percentChange = " ({$sign}" . number_format($percent, 1) . "%)";
            }
            
            $output .= "  - $subField: $oldStr → $newStr$percentChange\n";
        }
        return $output;
    }
    
    /**
     * Format array changes
     */
    private function formatArrayChange(string $field, $old, $new): string {
        $oldStr = empty($old) ? '[]' : implode(', ', $old);
        $newStr = empty($new) ? '[]' : implode(', ', $new);
        return "- **$field**: [$oldStr] → [$newStr]\n";
    }
    
    /**
     * Format time in seconds to human-readable
     */
    private function formatTime(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";
        
        return implode(' ', $parts);
    }
}

// Run diff generator
try {
    $oldFile = $argv[1] ?? __DIR__ . '/../data/units.json.backup';
    $newFile = $argv[2] ?? __DIR__ . '/../data/units.json';
    
    echo "Comparing:\n";
    echo "  Old: $oldFile\n";
    echo "  New: $newFile\n\n";
    
    $generator = new UnitDataDiffGenerator($oldFile, $newFile);
    $diff = $generator->generate();
    
    echo $diff;
    
    // Optionally save to file
    $outputFile = __DIR__ . '/../docs/unit_changes.md';
    file_put_contents($outputFile, $diff);
    echo "\nDiff saved to: $outputFile\n";
    
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
