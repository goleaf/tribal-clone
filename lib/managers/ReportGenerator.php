<?php
/**
 * ReportGenerator - Stateless battle report builder.
 */
class ReportGenerator
{
    /**
     * Build a report from a BattleResult-like array.
     *
     * @param array $result Battle resolution payload.
     * @param string $perspective 'attacker'|'defender'
     */
    public function generateReport(array $result, string $perspective): array
    {
        $isAttacker = $perspective === 'attacker';
        $intel = $isAttacker ? ($result['intel'] ?? null) : ($result['attacker_intel'] ?? null);

        return [
            'battle_id' => $result['battle_id'],
            'timestamp' => $result['timestamp'] ?? date('c'),
            'outcome' => $result['outcome'],
            'attacker_id' => $result['attacker_id'] ?? null,
            'defender_id' => $result['defender_id'] ?? null,
            'attacker_village' => $result['attacker_village'] ?? [],
            'defender_village' => $result['defender_village'] ?? [],
            'modifiers' => [
                'luck' => $result['luck'],
                'morale' => $result['morale'],
                'wall_multiplier' => $result['modifiers']['wall_multiplier'] ?? null,
                'night_bonus' => $result['modifiers']['night_bonus'] ?? null,
                'overstack_penalty' => $result['modifiers']['overstack_penalty'] ?? null,
                'environment' => $result['modifiers']['environment'] ?? null,
            ],
            'troops' => [
                'attacker_sent' => $result['attacker']['sent'],
                'attacker_lost' => $result['attacker']['lost'],
                'attacker_survivors' => $result['attacker']['survivors'],
                'defender_present' => $result['defender']['present'],
                'defender_lost' => $result['defender']['lost'],
                'defender_survivors' => $result['defender']['survivors'],
            ],
            'siege' => $result['siege'],
            'plunder' => $result['plunder'] ?? null,
            'vault_protection' => $result['vault_protection'] ?? null,
            'allegiance' => $result['allegiance'] ?? null,
            'intel' => $intel,
        ];
    }

    /**
     * Include defender intelligence if scouts survived.
     */
    public function includeIntelligence(array $report, array $intel): array
    {
        $report['intel'] = $intel;
        return $report;
    }

    /**
     * Redact defender intelligence.
     */
    public function redactIntelligence(array $report): array
    {
        $report['intel'] = null;
        return $report;
    }
}
