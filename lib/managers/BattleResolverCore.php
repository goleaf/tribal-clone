<?php
/**
 * BattleResolverCore - orchestrates combat using stateless components.
 */
class BattleResolverCore
{
    private CombatCalculator $combat;
    private ModifierApplier $modifiers;
    private SiegeHandler $siege;
    private PlunderCalculator $plunder;
    private ConquestHandler $conquest;
    private ReportGenerator $reports;
    private array $unitData;

    public function __construct(array $unitData)
    {
        $this->unitData = $unitData;
        $this->combat = new CombatCalculator($unitData);
        $this->modifiers = new ModifierApplier();
        $this->siege = new SiegeHandler();
        $this->plunder = new PlunderCalculator($unitData);
        $this->conquest = new ConquestHandler();
        $this->reports = new ReportGenerator();
    }

    /**
     * Resolve a battle with explicit inputs (stateless).
     *
     * @param array $input [
     *   'attacker_units' => [internal => count],
     *   'defender_units' => [internal => count],
     *   'attacker_points' => int,
     *   'defender_points' => int,
     *   'wall_level' => int,
     *   'resources' => ['wood'=>int,'clay'=>int,'iron'=>int],
     *   'world' => config array,
     *   'target_building' => ['name'=>string,'level'=>int]|null,
     *   'allegiance' => int,
     *   'attacker_id','defender_id','attacker_village','defender_village'
     * ]
     */
    public function resolveBattle(array $input): array
    {
        $start = microtime(true);
        $battleId = bin2hex(random_bytes(8));

        $attUnits = $input['attacker_units'] ?? [];
        $defUnits = $input['defender_units'] ?? [];
        $world = $input['world'] ?? [];
        $wallLevel = (int)($input['wall_level'] ?? 0);

        $defClassShares = $this->combat->getClassShares($defUnits);
        $off = $this->combat->calculateOffensivePower($attUnits, $defClassShares);
        $defPow = $this->combat->calculateDefensivePower($defUnits, $attUnits);

        $defPop = $this->calculatePopulation($defUnits);
        $mods = $this->modifiers->applyAllModifiers(
            $off,
            $defPow,
            $wallLevel,
            $defPop,
            (int)($input['defender_points'] ?? 0),
            (int)($input['attacker_points'] ?? 0),
            $world,
            $input['battle_time'] ?? null
        );

        $ratio = $mods['defense'] > 0 ? $mods['offense'] / $mods['defense'] : PHP_FLOAT_MAX;
        $casualties = $this->combat->calculateCasualties($ratio, $attUnits, $defUnits);
        $outcome = $this->combat->determineWinner($ratio);

        // Siege effects
        $survivingAtt = $casualties['attacker_survivors'];
        $rams = $survivingAtt['ram'] ?? 0;
        $catapults = $survivingAtt['catapult'] ?? 0;
        $wallAfter = $this->siege->applyRamDamage($wallLevel, $rams, $world);
        $buildingAfter = null;
        $buildingTarget = $input['target_building']['name'] ?? null;
        $buildingLevel = $input['target_building']['level'] ?? 0;
        if ($catapults > 0 && $outcome === 'attacker_win') {
            $buildingAfter = $this->siege->applyCatapultDamage($buildingLevel, $catapults, $world, true);
        } else {
            $buildingAfter = $buildingLevel;
        }

        // Plunder
        $plunderBlock = [
            'loot' => ['wood' => 0, 'clay' => 0, 'iron' => 0],
            'details' => null,
            'vault_protection' => ['percent' => (float)($world['vault_percent'] ?? 0), 'protected' => ['wood' => 0, 'clay' => 0, 'iron' => 0]],
        ];
        if ($outcome === 'attacker_win') {
            $resources = $input['resources'] ?? ['wood' => 0, 'clay' => 0, 'iron' => 0];
            $lootable = $this->plunder->calculateAvailableLoot(
                $resources,
                0,
                (float)($world['vault_percent'] ?? 0),
                null,
                (float)($world['plunder_dr_multiplier'] ?? 1.0)
            );
            $carry = $this->plunder->calculateCarryCapacity($survivingAtt, (float)($world['raid_bonus'] ?? 1.0));
            $distribution = $this->plunder->distributePlunder($lootable['lootable'], $carry);
            $plunderBlock = [
                'loot' => $distribution['loot'],
                'details' => [
                    'lootable_after_protection' => $lootable['lootable'],
                    'carry_used' => $distribution['carry_used'],
                    'carry_unused' => $distribution['carry_unused'],
                    'diminishing_returns' => $lootable['diminishing_returns'],
                ],
                'vault_protection' => [
                    'percent' => (float)($world['vault_percent'] ?? 0),
                    'protected' => $lootable['protected'],
                ],
            ];
        }

        // Conquest
        $conquestUnits = $this->countConquestUnits($survivingAtt);
        $allegianceStart = (int)($input['allegiance'] ?? 100);
        $conquestResult = $this->conquest->reduceAllegiance(
            $allegianceStart,
            $conquestUnits,
            $world,
            $outcome === 'attacker_win',
            (bool)($input['conquest_cooldown'] ?? false)
        );
        $allegianceEnd = $conquestResult['new_allegiance'];
        $captured = $this->conquest->checkCaptureConditions($allegianceEnd);
        if ($captured) {
            $allegianceEnd = $this->conquest->applyPostCaptureAllegiance($world);
        }

        $result = [
            'battle_id' => $battleId,
            'timestamp' => date('c'),
            'outcome' => $outcome,
            'luck' => $mods['modifiers']['luck'],
            'morale' => $mods['modifiers']['morale'],
            'ratio' => $ratio,
            'attacker' => [
                'sent' => $attUnits,
                'lost' => $casualties['attacker_losses'],
                'survivors' => $survivingAtt,
            ],
            'defender' => [
                'present' => $defUnits,
                'lost' => $casualties['defender_losses'],
                'survivors' => $casualties['defender_survivors'],
            ],
            'siege' => [
                'wall' => ['start' => $wallLevel, 'end' => $wallAfter],
                'building' => [
                    'target' => $buildingTarget,
                    'start' => $buildingLevel,
                    'end' => $buildingAfter,
                ],
            ],
            'plunder' => $plunderBlock['loot'],
            'plunder_details' => $plunderBlock['details'],
            'vault_protection' => $plunderBlock['vault_protection'],
            'allegiance' => [
                'start' => $allegianceStart,
                'end' => $allegianceEnd,
                'captured' => $captured,
                'drop' => $conquestResult['dropped'],
                'blocked' => $conquestResult['blocked'],
                'reason' => $conquestResult['reason'],
            ],
            'modifiers' => [
                'wall_multiplier' => $this->siege->calculateWallMultiplier($wallAfter),
                'night_bonus' => $mods['modifiers']['night_bonus'] ?? null,
                'overstack_penalty' => $mods['modifiers']['overstack_penalty'] ?? null,
                'environment' => [
                    'terrain_attack' => $mods['modifiers']['terrain_attack'] ?? null,
                    'terrain_defense' => $mods['modifiers']['terrain_defense'] ?? null,
                    'weather_attack' => $mods['modifiers']['weather_attack'] ?? null,
                    'weather_defense' => $mods['modifiers']['weather_defense'] ?? null,
                ],
            ],
            'attacker_id' => $input['attacker_id'] ?? null,
            'defender_id' => $input['defender_id'] ?? null,
            'attacker_village' => $input['attacker_village'] ?? [],
            'defender_village' => $input['defender_village'] ?? [],
        ];

        $metrics = $this->emitMetrics($result, $start);

        $attackerReport = $this->reports->generateReport($result, 'attacker');
        $defenderReport = $this->reports->generateReport($result, 'defender');

        return [
            'result' => $result,
            'reports' => [
                'attacker' => $attackerReport,
                'defender' => $defenderReport,
            ],
            'metrics' => $metrics,
        ];
    }

    private function emitMetrics(array $result, float $start): array
    {
        $latencyMs = (int)round((microtime(true) - $start) * 1000);
        return [
            'battle_id' => $result['battle_id'],
            'resolver_latency_ms' => $latencyMs,
            'outcome' => $result['outcome'],
            'had_siege' => ($result['siege']['wall']['start'] ?? 0) > ($result['siege']['wall']['end'] ?? 0),
            'had_conquest' => ($result['allegiance']['drop'] ?? 0) > 0,
        ];
    }

    private function calculatePopulation(array $units): int
    {
        $pop = 0;
        foreach ($units as $type => $count) {
            $pop += ($this->unitData[$type]['pop'] ?? 0) * $count;
        }
        return $pop;
    }

    private function countConquestUnits(array $units): int
    {
        $conquestNames = ['noble', 'chieftain', 'senator', 'chief', 'envoy', 'standard_bearer'];
        $count = 0;
        foreach ($conquestNames as $name) {
            $count += $units[$name] ?? 0;
        }
        return $count;
    }
}
