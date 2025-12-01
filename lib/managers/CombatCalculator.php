<?php
/**
 * CombatCalculator - Stateless component for combat power and casualty calculations
 * 
 * Handles:
 * - Offensive power calculation with class multipliers
 * - Defensive power calculation with weighted defense
 * - Casualty calculation using ratio^1.5 formula
 * - Winner determination based on ratio threshold
 * - Unit conservation validation
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4
 */
class CombatCalculator
{
    private $unitData;
    
    // Casualty calculation constant
    private const CASUALTY_EXPONENT = 1.5;
    
    // Unit class mappings
    private const UNIT_CLASSES = [
        // Legacy/internal names
        'spear' => 'infantry',
        'sword' => 'infantry',
        'axe' => 'infantry',
        'archer' => 'archer',
        'scout' => 'cavalry',
        'light' => 'cavalry',
        'heavy' => 'cavalry',
        'ram' => 'infantry',
        'catapult' => 'infantry',
        'noble' => 'cavalry',
        'paladin' => 'cavalry',
        'marcher' => 'archer',

        // Design-roster names
        'tribesman' => 'infantry',
        'spearguard' => 'infantry',
        'axe_warrior' => 'infantry',
        'bowman' => 'archer',
        'slinger' => 'archer',
        'raider' => 'cavalry',
        'lancer' => 'cavalry',
        'horse_archer' => 'archer',
        'battering_ram' => 'infantry',
        'supply_cart' => 'infantry',
        'berserker' => 'infantry',
        'shieldmaiden' => 'infantry',
        'warlord' => 'cavalry',
        'rune_priest' => 'infantry'
    ];
    
    /**
     * Constructor
     * 
     * @param array $unitData Unit data loaded from JSON or database
     */
    public function __construct(array $unitData)
    {
        $this->unitData = $unitData;
    }
    
    /**
     * Calculate total offensive power with class multipliers
     * 
     * Uses RPS (Rock-Paper-Scissors) mechanics:
     * - Cavalry > Archer (ranged)
     * - Archer > Infantry
     * - Infantry > Cavalry (spears/pikes)
     * 
     * @param array $attackerUnits Map of unit type => count
     * @param array $defenderClassShares Share of defender units by class
     * @return float Total offensive power
     */
    public function calculateOffensivePower(array $attackerUnits, array $defenderClassShares = []): float
    {
        $totalOff = 0;
        
        foreach ($attackerUnits as $unitType => $count) {
            if ($count > 0 && isset($this->unitData[$unitType])) {
                $baseOff = $this->unitData[$unitType]['off'];
                $class = self::UNIT_CLASSES[$unitType] ?? 'infantry';
                $mult = $this->getClassAttackMultiplier($class, $defenderClassShares);
                $totalOff += ($baseOff * $mult) * $count;
            }
        }
        
        return $totalOff;
    }
    
    /**
     * Calculate total defensive power with weighted defense
     * 
     * Defenders use different defense values against different attacker classes:
     * - def.gen: defense against infantry
     * - def.cav: defense against cavalry
     * - def.arc: defense against archers
     * 
     * Defense is weighted by attacker class distribution
     * 
     * @param array $defenderUnits Map of unit type => count
     * @param array $attackerUnits Map of unit type => count (for class distribution)
     * @return float Total defensive power
     */
    public function calculateDefensivePower(array $defenderUnits, array $attackerUnits): float
    {
        // Count attacking units by class
        $attackerClasses = $this->countUnitsByClass($attackerUnits);
        
        // Calculate weighted defense
        $totalDef = 0;
        
        foreach ($defenderUnits as $unitType => $count) {
            if ($count > 0 && isset($this->unitData[$unitType])) {
                $unitDef = $this->unitData[$unitType]['def'];
                
                // Determine which defense value to use based on attacker composition
                $defValue = $this->getWeightedDefense($unitDef, $attackerClasses);
                $totalDef += $defValue * $count;
            }
        }
        
        return $totalDef;
    }
    
    /**
     * Calculate casualties using ratio^1.5 formula
     * 
     * Formula:
     * - If ratio >= 1 (attacker wins):
     *   - Defender loss factor: 1.0 (total loss)
     *   - Attacker loss factor: 1 / (ratio^1.5)
     * - If ratio < 1 (defender holds):
     *   - Attacker loss factor: 1.0 (total loss)
     *   - Defender loss factor: ratio^1.5
     * 
     * @param float $ratio Battle ratio (totalOff / effectiveDef)
     * @param array $attackerUnits Map of unit type => count
     * @param array $defenderUnits Map of unit type => count
     * @return array ['attacker_losses' => array, 'attacker_survivors' => array, 'defender_losses' => array, 'defender_survivors' => array]
     */
    public function calculateCasualties(float $ratio, array $attackerUnits, array $defenderUnits): array
    {
        // Determine winner and loss factors
        $attackerWon = $ratio >= 1.0;
        
        if ($attackerWon) {
            $attackerLossFactor = 1.0 / pow($ratio, self::CASUALTY_EXPONENT);
            $defenderLossFactor = 1.0;
        } else {
            $attackerLossFactor = 1.0;
            $defenderLossFactor = pow($ratio, self::CASUALTY_EXPONENT);
        }
        
        // Apply losses
        $attackerSurvivors = $this->applyLosses($attackerUnits, $attackerLossFactor);
        $defenderSurvivors = $this->applyLosses($defenderUnits, $defenderLossFactor);
        
        // Calculate losses
        $attackerLosses = $this->calculateLosses($attackerUnits, $attackerSurvivors);
        $defenderLosses = $this->calculateLosses($defenderUnits, $defenderSurvivors);
        
        // Validate unit conservation
        $this->validateUnitConservation($attackerUnits, $attackerLosses, $attackerSurvivors);
        $this->validateUnitConservation($defenderUnits, $defenderLosses, $defenderSurvivors);
        
        return [
            'attacker_losses' => $attackerLosses,
            'attacker_survivors' => $attackerSurvivors,
            'defender_losses' => $defenderLosses,
            'defender_survivors' => $defenderSurvivors
        ];
    }
    
    /**
     * Determine battle winner based on ratio threshold
     * 
     * @param float $ratio Battle ratio (totalOff / effectiveDef)
     * @return string 'attacker_win' or 'defender_hold'
     */
    public function determineWinner(float $ratio): string
    {
        return $ratio >= 1.0 ? 'attacker_win' : 'defender_hold';
    }
    
    /**
     * Merge garrison and support forces into a single defending force
     * 
     * @param array $garrison Village owner's troops
     * @param array $support Allied support troops
     * @return array Combined defending forces
     */
    public function mergeDefendingForces(array $garrison, array $support): array
    {
        $merged = [];
        
        // Add garrison units
        foreach ($garrison as $unitType => $count) {
            $merged[$unitType] = ($merged[$unitType] ?? 0) + $count;
        }
        
        // Add support units
        foreach ($support as $unitType => $count) {
            $merged[$unitType] = ($merged[$unitType] ?? 0) + $count;
        }
        
        return $merged;
    }
    
    /**
     * Get class shares of defender units for RPS bonuses
     * 
     * @param array $units Map of unit type => count
     * @return array ['infantry' => float, 'cavalry' => float, 'archer' => float]
     */
    public function getClassShares(array $units): array
    {
        $counts = $this->countUnitsByClass($units);
        $total = array_sum($counts);
        
        if ($total <= 0) {
            return ['infantry' => 0.0, 'cavalry' => 0.0, 'archer' => 0.0];
        }
        
        return [
            'infantry' => $counts['infantry'] / $total,
            'cavalry' => $counts['cavalry'] / $total,
            'archer' => $counts['archer'] / $total,
        ];
    }
    
    /**
     * Apply RPS multipliers based on attacker class and defender composition
     * 
     * @param string $attackerClass Attacker unit class
     * @param array $defenderShares Defender class shares
     * @return float Attack multiplier
     */
    private function getClassAttackMultiplier(string $attackerClass, array $defenderShares): float
    {
        $infShare = $defenderShares['infantry'] ?? 0.0;
        $cavShare = $defenderShares['cavalry'] ?? 0.0;
        $rngShare = $defenderShares['archer'] ?? 0.0;
        $mult = 1.0;

        if ($attackerClass === 'cavalry' && $rngShare > 0) {
            $mult += 0.25 * $rngShare; // Cav better into ranged in open
        } elseif ($attackerClass === 'archer' && $infShare > 0) {
            $mult += 0.15 * $infShare; // Ranged better into infantry blobs
        } elseif ($attackerClass === 'infantry' && $cavShare > 0) {
            $mult += 0.10 * $cavShare; // Spears/pikes blunt cav
        }

        return $mult;
    }
    
    /**
     * Count units by their class (infantry, cavalry, archer)
     * 
     * @param array $units Map of unit type => count
     * @return array ['infantry' => int, 'cavalry' => int, 'archer' => int]
     */
    private function countUnitsByClass(array $units): array
    {
        $classes = ['infantry' => 0, 'cavalry' => 0, 'archer' => 0];
        
        foreach ($units as $unitType => $count) {
            if ($count > 0 && isset(self::UNIT_CLASSES[$unitType])) {
                $class = self::UNIT_CLASSES[$unitType];
                $classes[$class] += $count;
            }
        }
        
        return $classes;
    }
    
    /**
     * Get weighted defense value based on attacker composition
     * 
     * @param array $defValues Defense values ['gen' => int, 'cav' => int, 'arc' => int]
     * @param array $attackerClasses Attacker class counts
     * @return float Weighted defense value
     */
    private function getWeightedDefense(array $defValues, array $attackerClasses): float
    {
        $totalAttackers = array_sum($attackerClasses);
        
        if ($totalAttackers === 0) {
            return $defValues['gen'];
        }
        
        $weightedDef = 0;
        
        // Weight defense by attacker class distribution
        if ($attackerClasses['infantry'] > 0) {
            $weight = $attackerClasses['infantry'] / $totalAttackers;
            $weightedDef += $defValues['gen'] * $weight;
        }
        
        if ($attackerClasses['cavalry'] > 0) {
            $weight = $attackerClasses['cavalry'] / $totalAttackers;
            $weightedDef += $defValues['cav'] * $weight;
        }
        
        if ($attackerClasses['archer'] > 0) {
            $weight = $attackerClasses['archer'] / $totalAttackers;
            $weightedDef += ($defValues['arc'] ?? $defValues['gen']) * $weight;
        }
        
        return $weightedDef;
    }
    
    /**
     * Apply loss factor to units and return survivors
     * 
     * @param array $units Map of unit type => count
     * @param float $lossFactor Loss factor (0.0 to 1.0)
     * @return array Map of unit type => survivor count
     */
    private function applyLosses(array $units, float $lossFactor): array
    {
        $survivors = [];
        
        foreach ($units as $unitType => $count) {
            $lost = (int)ceil($count * $lossFactor);
            $survivors[$unitType] = max(0, $count - $lost);
        }
        
        return $survivors;
    }
    
    /**
     * Calculate losses by comparing original and survivors
     * 
     * @param array $original Original unit counts
     * @param array $survivors Surviving unit counts
     * @return array Map of unit type => loss count
     */
    private function calculateLosses(array $original, array $survivors): array
    {
        $losses = [];
        
        foreach ($original as $unitType => $count) {
            $survivorCount = $survivors[$unitType] ?? 0;
            $losses[$unitType] = $count - $survivorCount;
        }
        
        return $losses;
    }
    
    /**
     * Validate unit conservation: sent - lost = survivors
     * 
     * @param array $sent Original unit counts
     * @param array $lost Lost unit counts
     * @param array $survivors Surviving unit counts
     * @throws Exception If conservation is violated
     */
    private function validateUnitConservation(array $sent, array $lost, array $survivors): void
    {
        foreach ($sent as $unitType => $sentCount) {
            $lostCount = $lost[$unitType] ?? 0;
            $survivorCount = $survivors[$unitType] ?? 0;
            
            if ($sentCount !== ($lostCount + $survivorCount)) {
                throw new Exception(
                    "Unit conservation violated for {$unitType}: " .
                    "sent={$sentCount}, lost={$lostCount}, survivors={$survivorCount}"
                );
            }
        }
    }
}
