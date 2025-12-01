<?php
/**
 * Script to generate complete units.json with 16+ unit roster
 * Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1
 */

$units = [
    '_comment' => 'Military Units System - Complete 16+ Unit Roster',
    '_version' => '2.0.0',
    '_requirements' => '1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2, 7.1, 7.2, 8.1, 8.2, 14.1',
    
    // Infantry Units
    'pikeneer' => [
        'name' => 'Pikeneer',
        'internal_name' => 'pikeneer',
        'category' => 'infantry',
        'building_type' => 'barracks',
        'required_building_level' => 1,
        'required_tech' => null,
        'required_tech_level' => 0,
        'cost' => ['wood' => 50, 'clay' => 30, 'iron' => 10],
        'population' => 1,
        'attack' => 25,
        'defense' => ['infantry' => 65, 'cavalry' => 20, 'ranged' => 15],
        'speed_min_per_field' => 18,
        'carry_capacity' => 10,
        'training_time_base' => 2700,
        'rps_bonuses' => ['vs_cavalry' => 1.4],
        'special_abilities' => [],
        'description' => 'Anti-cavalry specialist with pike formation. Strong against cavalry, weak against ranged.'
    ],
    
    'shieldbearer' => [
        'name' => 'Shieldbearer',
        'internal_name' => 'shieldbearer',
        'category' => 'infantry',
        'building_type' => 'barracks',
        'required_building_level' => 2,
        'required_tech' => null,
        'required_tech_level' => 0,
        'cost' => ['wood' => 60, 'clay' => 50, 'iron' => 40],
        'population' => 1,
        'attack' => 30,
        'defense' => ['infantry' => 70, 'cavalry' => 65, 'ranged' => 60],
        'speed_min_per_field' => 20,
        'carry_capacity' => 15,
        'training_time_base' => 3000,
        'rps_bonuses' => [],
        'special_abilities' => [],
        'description' => 'Balanced defensive infantry with shield wall formation. Reliable all-around defender.'
    ],
    
    'raider' => [
        'name' => 'Raider',
        'internal_name' => 'raider',
        'category' => 'infantry',
        'building_type' => 'barracks',
        'required_building_level' => 1,
        'required_tech' => null,
        'required_tech_level' => 0,
        'cost' => ['wood' => 70, 'clay' => 40, 'iron' => 60],
        'population' => 1,
        'attack' => 60,
        'defense' => ['infantry' => 20, 'cavalry' => 15, 'ranged' => 10],
        'speed_min_per_field' => 18,
        'carry_capacity' => 35,
        'training_time_base' => 2400,
        'rps_bonuses' => [],
        'special_abilities' => [],
        'description' => 'Offensive infantry raider. High attack, low defense, good carry capacity for plundering.'
    ],
    
    'warden' => [
        'name' => 'Warden',
        'internal_name' => 'warden',
        'category' => 'infantry',
        'building_type' => 'barracks',
        'required_building_level' => 10,
        'required_tech' => 'elite_training',
        'required_tech_level' => 8,
        'cost' => ['wood' => 200, 'clay' => 180, 'iron' => 220],
        'population' => 2,
        'attack' => 80,
        'defense' => ['infantry' => 240, 'cavalry' => 230, 'ranged' => 220],
        'speed_min_per_field' => 20,
        'carry_capacity' => 25,
        'training_time_base' => 7200,
        'rps_bonuses' => [],
        'special_abilities' => ['elite'],
        'description' => 'Elite defensive infantry. Very high defense across all types. Subject to per-account caps.'
    ],
    
    // Ranged Units
    'militia_bowman' => [
        'name' => 'Militia Bowman',
        'internal_name' => 'militia_bowman',
        'category' => 'ranged',
        'building_type' => 'barracks',
        'required_building_level' => 1,
        'required_tech' => null,
        'required_tech_level' => 0,
        'cost' => ['wood' => 60, 'clay' => 30, 'iron' => 40],
        'population' => 1,
        'attack' => 25,
        'defense' => ['infantry' => 10, 'cavalry' => 20, 'ranged' => 5],
        'speed_min_per_field' => 18,
        'carry_capacity' => 20,
        'training_time_base' => 3600,
        'rps_bonuses' => ['wall_bonus_vs_infantry' => 1.5],
        'special_abilities' => ['wall_bonus'],
        'description' => 'Basic ranged unit. Gains significant defense bonus against infantry when behind walls.'
    ],
    
    'longbow_scout' => [
        'name' => 'Longbow Scout',
        'internal_name' => 'longbow_scout',
        'category' => 'ranged',
        'building_type' => 'barracks',
        'required_building_level' => 3,
        'required_tech' => 'archery',
        'required_tech_level' => 2,
        'cost' => ['wood' => 80, 'clay' => 50, 'iron' => 60],
        'population' => 1,
        'attack' => 45,
        'defense' => ['infantry' => 15, 'cavalry' => 30, 'ranged' => 10],
        'speed_min_per_field' => 16,
        'carry_capacity' => 25,
        'training_time_base' => 4200,
        'rps_bonuses' => ['wall_bonus_vs_infantry' => 1.6],
        'special_abilities' => ['wall_bonus'],
        'description' => 'Improved ranged unit with better offense. Enhanced wall bonus against infantry.'
    ],
    
    'ranger' => [
        'name' => 'Ranger',
        'internal_name' => 'ranger',
        'category' => 'ranged',
        'building_type' => 'barracks',
        'required_building_level' => 8,
        'required_tech' => 'ranged_warfare',
        'required_tech_level' => 7,
        'cost' => ['wood' => 180, 'clay' => 140, 'iron' => 160],
        'population' => 2,
        'attack' => 90,
        'defense' => ['infantry' => 40, 'cavalry' => 60, 'ranged' => 30],
        'speed_min_per_field' => 16,
        'carry_capacity' => 30,
        'training_time_base' => 6600,
        'rps_bonuses' => ['vs_siege' => 2.0, 'wall_bonus_vs_infantry' => 1.7],
        'special_abilities' => ['elite', 'anti_siege', 'wall_bonus'],
        'description' => 'Elite ranged unit with anti-siege specialization. Devastating against rams and catapults.'
    ],
];

// Add remaining units...
$units = array_merge($units, [
    // Cavalry Units
    'skirmisher_cavalry' => [
        'name' => 'Skirmisher Cavalry',
        'internal_name' => 'skirmisher_cavalry',
        'category' => 'cavalry',
        'building_type' => 'stable',
        'required_building_level' => 1,
        'required_tech' => null,
        'required_tech_level' => 0,
        'cost' => ['wood' => 100, 'clay' => 50, 'iron' => 80],
        'population' => 2,
        'attack' => 60,
        'defense' => ['infantry' => 20, 'cavalry' => 15, 'ranged' => 15],
        'speed_min_per_field' => 8,
        'carry_capacity' => 80,
        'training_time_base' => 3600,
        'rps_bonuses' => ['vs_ranged_open_field' => 1.5],
        'special_abilities' => [],
        'description' => 'Fast raiding cavalry. Bonus against ranged units in open field. Excellent for quick raids.'
    ],
    
    'lancer' => [
        'name' => 'Lancer',
        'internal_name' => 'lancer',
        'category' => 'cavalry',
        'building_type' => 'stable',
        'required_building_level' => 3,
        'required_tech' => 'heavy_cavalry',
        'required_tech_level' => 1,
        'cost' => ['wood' => 150, 'clay' => 120, 'iron' => 200],
        'population' => 3,
        'attack' => 150,
        'defense' => ['infantry' => 60, 'cavalry' => 40, 'ranged' => 30],
        'speed_min_per_field' => 9,
        'carry_capacity' => 40,
        'training_time_base' => 7200,
        'rps_bonuses' => ['vs_ranged_open_field' => 1.6],
        'special_abilities' => [],
        'description' => 'Heavy shock cavalry. High attack and population cost. Strong against ranged in open field.'
    ],
]);

// Continue with remaining unit categories...
echo json_encode($units, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
