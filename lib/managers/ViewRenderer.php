<?php
declare(strict_types=1);

/**
 * ViewRenderer - WAP-style HTML renderer for minimalist interfaces.
 * 
 * Implements Requirements 3.1, 3.2, 3.3, 3.4, 3.5:
 * - Compact HTML tables with minimal markup
 * - Text-only resource displays with rates
 * - Hyperlink navigation menus
 * - Formatted text tables for combat results
 * - Suitable for WAP constraints (low bandwidth, simple HTML)
 */
class ViewRenderer
{
    private $conn;
    private $buildingManager;
    private $resourceManager;

    public function __construct($conn, $buildingManager = null, $resourceManager = null)
    {
        $this->conn = $conn;
        $this->buildingManager = $buildingManager;
        $this->resourceManager = $resourceManager;
    }

    /**
     * Render village overview as compact HTML table.
     * Implements Requirement 3.1: Compact HTML table with buildings/resources/movements.
     * 
     * @param array $village Village data
     * @param array $buildings Building data
     * @param array $movements Movement data (incoming/outgoing)
     * @return string HTML table
     */
    public function renderVillageOverview(array $village, array $buildings, array $movements): string
    {
        $html = '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        
        // Left column: Buildings
        $html .= '<td valign="top" width="33%">' . "\n";
        $html .= '<b>Buildings</b><br>' . "\n";
        foreach ($buildings as $building) {
            $name = htmlspecialchars($building['name'] ?? 'Unknown');
            $level = (int)($building['level'] ?? 0);
            $internal = htmlspecialchars($building['internal_name'] ?? '');
            $html .= sprintf(
                '<a href="?building=%s">%s</a> (Lvl %d)<br>' . "\n",
                $internal,
                $name,
                $level
            );
        }
        $html .= '</td>' . "\n";
        
        // Center column: Resources
        $html .= '<td valign="top" width="34%">' . "\n";
        $html .= '<b>Resources</b><br>' . "\n";
        
        // Get Hiding Place protection if buildingManager is available
        $hidingPlaceProtection = 0;
        if ($this->buildingManager && isset($village['id'])) {
            $hidingPlaceProtection = $this->buildingManager->getHidingPlaceCapacity((int)$village['id']);
        }
        
        $html .= $this->renderResourceBar(
            [
                'wood' => $village['wood'] ?? 0,
                'clay' => $village['clay'] ?? 0,
                'iron' => $village['iron'] ?? 0
            ],
            [
                'wood' => $village['wood_rate'] ?? 0,
                'clay' => $village['clay_rate'] ?? 0,
                'iron' => $village['iron_rate'] ?? 0
            ],
            (int)($village['warehouse_capacity'] ?? 1000),
            $hidingPlaceProtection
        );
        $html .= '<br><b>Population</b><br>' . "\n";
        $html .= sprintf(
            '%d / %d<br>' . "\n",
            (int)($village['population'] ?? 0),
            (int)($village['farm_capacity'] ?? 0)
        );
        $html .= '</td>' . "\n";
        
        // Right column: Movements
        $html .= '<td valign="top" width="33%">' . "\n";
        $html .= '<b>Movements</b><br>' . "\n";
        
        if (empty($movements['incoming']) && empty($movements['outgoing'])) {
            $html .= 'No movements<br>' . "\n";
        } else {
            if (!empty($movements['incoming'])) {
                $html .= '<b>Incoming:</b><br>' . "\n";
                foreach ($movements['incoming'] as $movement) {
                    $html .= $this->formatMovementEntry($movement, 'incoming');
                }
            }
            if (!empty($movements['outgoing'])) {
                $html .= '<b>Outgoing:</b><br>' . "\n";
                foreach ($movements['outgoing'] as $movement) {
                    $html .= $this->formatMovementEntry($movement, 'outgoing');
                }
            }
        }
        $html .= '</td>' . "\n";
        
        $html .= '</tr>' . "\n";
        $html .= '</table>' . "\n";
        
        return $html;
    }

    /**
     * Format a single movement entry.
     * 
     * @param array $movement Movement data
     * @param string $type 'incoming' or 'outgoing'
     * @return string Formatted HTML
     */
    private function formatMovementEntry(array $movement, string $type): string
    {
        $arrivalTime = $movement['arrival_time'] ?? null;
        $origin = htmlspecialchars($movement['origin'] ?? 'Unknown');
        $destination = htmlspecialchars($movement['destination'] ?? 'Unknown');
        $attackType = htmlspecialchars($movement['attack_type'] ?? 'Attack');
        
        $timeStr = $arrivalTime ? date('H:i:s', strtotime($arrivalTime)) : 'Unknown';
        
        if ($type === 'incoming') {
            return sprintf(
                '%s from %s at %s<br>' . "\n",
                $attackType,
                $origin,
                $timeStr
            );
        } else {
            return sprintf(
                '%s to %s at %s<br>' . "\n",
                $attackType,
                $destination,
                $timeStr
            );
        }
    }

    /**
     * Render building list as HTML table rows with upgrade links.
     * Implements Requirement 3.2: Table rows with name, level, cost, time, upgrade link.
     * 
     * @param array $buildings Array of building data
     * @param array $village Village data for resource checking
     * @return string HTML table
     */
    public function renderBuildingList(array $buildings, array $village): string
    {
        $html = '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Building</th>' . "\n";
        $html .= '<th>Level</th>' . "\n";
        $html .= '<th>Upgrade Cost</th>' . "\n";
        $html .= '<th>Time</th>' . "\n";
        $html .= '<th>Action</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        foreach ($buildings as $building) {
            $name = htmlspecialchars($building['name'] ?? 'Unknown');
            $level = (int)($building['level'] ?? 0);
            $internal = htmlspecialchars($building['internal_name'] ?? '');
            $nextLevel = $level + 1;
            
            $html .= '<tr>' . "\n";
            $html .= sprintf('<td>%s</td>' . "\n", $name);
            $html .= sprintf('<td>%d</td>' . "\n", $level);
            
            // Upgrade cost
            $costs = $building['upgrade_costs'] ?? null;
            if ($costs && $level < ($building['max_level'] ?? 30)) {
                $costStr = sprintf(
                    '%dW, %dC, %dI',
                    (int)($costs['wood'] ?? 0),
                    (int)($costs['clay'] ?? 0),
                    (int)($costs['iron'] ?? 0)
                );
                $html .= sprintf('<td>%s</td>' . "\n", $costStr);
                
                // Time
                $time = $building['upgrade_time_seconds'] ?? 0;
                $timeStr = $this->formatDuration($time);
                $html .= sprintf('<td>%s</td>' . "\n", $timeStr);
                
                // Action
                $canUpgrade = $building['can_upgrade'] ?? false;
                if ($canUpgrade) {
                    $html .= sprintf(
                        '<td><a href="?upgrade=%s">Upgrade to %d</a></td>' . "\n",
                        $internal,
                        $nextLevel
                    );
                } else {
                    $reason = htmlspecialchars($building['upgrade_not_available_reason'] ?? 'Cannot upgrade');
                    $html .= sprintf('<td>%s</td>' . "\n", $reason);
                }
            } else {
                $html .= '<td>-</td>' . "\n";
                $html .= '<td>-</td>' . "\n";
                $html .= '<td>Max level</td>' . "\n";
            }
            
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        
        return $html;
    }

    /**
     * Render resource bar as text-only display with rates.
     * Implements Requirement 3.3: Text-only resource display with rates.
     * Implements Requirement 9.3: Display both Warehouse capacity and Hiding Place protection.
     * 
     * @param array $resources Current resource amounts ['wood' => int, 'clay' => int, 'iron' => int]
     * @param array $rates Production rates ['wood' => float, 'clay' => float, 'iron' => float]
     * @param int $capacity Warehouse capacity
     * @param int $hidingPlaceProtection Hiding Place protection per resource (optional)
     * @return string Formatted text display
     */
    public function renderResourceBar(array $resources, array $rates, int $capacity, int $hidingPlaceProtection = 0): string
    {
        $html = '';
        
        foreach (['wood', 'clay', 'iron'] as $resourceType) {
            $amount = (int)($resources[$resourceType] ?? 0);
            $rate = (float)($rates[$resourceType] ?? 0);
            $resourceName = ucfirst($resourceType);
            
            // Use ResourceManager's format if available
            if ($this->resourceManager && method_exists($this->resourceManager, 'formatResourceDisplay')) {
                $html .= $this->resourceManager->formatResourceDisplay($resourceName, $amount, $rate) . '<br>' . "\n";
            } else {
                // Fallback format: "[Resource]: [Amount] (+[Rate]/hr)"
                $html .= sprintf(
                    '%s: %d (+%.1f/hr)<br>' . "\n",
                    $resourceName,
                    $amount,
                    $rate
                );
            }
        }
        
        $html .= sprintf('Capacity: %d<br>' . "\n", $capacity);
        
        // Display Hiding Place protection if present
        if ($hidingPlaceProtection > 0) {
            $html .= sprintf('Protected: %d per resource<br>' . "\n", $hidingPlaceProtection);
        }
        
        return $html;
    }

    /**
     * Render navigation header as hyperlink menu.
     * Implements Requirement 3.4: Hyperlink menu for main sections.
     * 
     * @return string HTML navigation menu
     */
    public function renderNavigation(): string
    {
        $links = [
            ['href' => '/game/game.php', 'label' => 'Village'],
            ['href' => '/units/recruit_units.php', 'label' => 'Troops'],
            ['href' => '/ajax/trade/get_market_data.php', 'label' => 'Market'],
            ['href' => '/research/research.php', 'label' => 'Research'],
            ['href' => '/messages/reports.php', 'label' => 'Reports'],
            ['href' => '/messages/messages.php', 'label' => 'Messages'],
            ['href' => '/player/tribe.php', 'label' => 'Alliance'],
            ['href' => '/player/player.php', 'label' => 'Profile']
        ];
        
        $html = '<p>';
        $linkHtml = [];
        foreach ($links as $link) {
            $linkHtml[] = sprintf(
                '<a href="%s">%s</a>',
                htmlspecialchars($link['href']),
                htmlspecialchars($link['label'])
            );
        }
        $html .= implode(' | ', $linkHtml);
        $html .= '</p>' . "\n";
        
        return $html;
    }

    /**
     * Render battle report as formatted text tables.
     * Implements Requirement 3.5: Formatted text tables for combat results.
     * Implements Requirement 6.6: 16x16 icon support for victory/defeat/scout indicators.
     * 
     * @param array $report Battle report data
     * @return string HTML formatted report
     */
    public function renderBattleReport(array $report): string
    {
        $html = '<h3>Battle Report</h3>' . "\n";
        
        // Add 16x16 icon for victory/defeat/scout (Requirement 6.6)
        $icon = $this->getBattleReportIcon($report);
        if ($icon) {
            $html .= $icon . "\n";
        }
        
        // Battle info
        $html .= '<p>' . "\n";
        $html .= sprintf(
            '<b>Time:</b> %s<br>' . "\n",
            htmlspecialchars($report['timestamp'] ?? date('Y-m-d H:i:s'))
        );
        $outcome = isset($report['outcome']) ? ucfirst($report['outcome']) : 'Unknown';
        $html .= sprintf(
            '<b>Outcome:</b> %s<br>' . "\n",
            htmlspecialchars($outcome)
        );
        $html .= '</p>' . "\n";
        
        // Attacker village
        $attackerVillage = $report['attacker_village'] ?? [];
        $html .= sprintf(
            '<p><b>Attacker:</b> %s (%d|%d)</p>' . "\n",
            htmlspecialchars($attackerVillage['name'] ?? 'Unknown'),
            (int)($attackerVillage['x_coord'] ?? 0),
            (int)($attackerVillage['y_coord'] ?? 0)
        );
        
        // Defender village
        $defenderVillage = $report['defender_village'] ?? [];
        $html .= sprintf(
            '<p><b>Defender:</b> %s (%d|%d)</p>' . "\n",
            htmlspecialchars($defenderVillage['name'] ?? 'Unknown'),
            (int)($defenderVillage['x_coord'] ?? 0),
            (int)($defenderVillage['y_coord'] ?? 0)
        );
        
        // Modifiers
        $modifiers = $report['modifiers'] ?? [];
        $html .= '<p><b>Modifiers:</b><br>' . "\n";
        $html .= sprintf('Luck: %.1f%%<br>' . "\n", ($modifiers['luck'] ?? 0) * 100);
        $html .= sprintf('Morale: %.1f%%<br>' . "\n", ($modifiers['morale'] ?? 100));
        if (isset($modifiers['wall_multiplier'])) {
            $html .= sprintf('Wall Bonus: %.2fx<br>' . "\n", $modifiers['wall_multiplier']);
        }
        $html .= '</p>' . "\n";
        
        // Troops table
        $troops = $report['troops'] ?? [];
        $html .= $this->renderTroopsTable($troops);
        
        // Plunder
        if (isset($report['plunder'])) {
            $plunder = $report['plunder'];
            $html .= '<p><b>Plunder:</b><br>' . "\n";
            $html .= sprintf(
                'Wood: %d, Clay: %d, Iron: %d<br>' . "\n",
                (int)($plunder['wood'] ?? 0),
                (int)($plunder['clay'] ?? 0),
                (int)($plunder['iron'] ?? 0)
            );
            $html .= '</p>' . "\n";
        }
        
        // Allegiance/Loyalty
        if (isset($report['allegiance'])) {
            $allegiance = $report['allegiance'];
            $html .= '<p><b>Loyalty:</b><br>' . "\n";
            $html .= sprintf(
                'Before: %d, After: %d (-%d)<br>' . "\n",
                (int)($allegiance['before'] ?? 100),
                (int)($allegiance['after'] ?? 100),
                (int)($allegiance['drop'] ?? 0)
            );
            $html .= '</p>' . "\n";
        }
        
        return $html;
    }

    /**
     * Get 16x16 icon for battle report based on outcome.
     * Implements Requirement 6.6: 16x16 icon support for victory/defeat/scout indicators.
     * 
     * @param array $report Battle report data
     * @return string HTML img tag or empty string
     */
    private function getBattleReportIcon(array $report): string
    {
        $outcome = $report['outcome'] ?? '';
        $attackType = $report['attack_type'] ?? 'attack';
        
        // Determine icon based on outcome and attack type
        $iconFile = '';
        $altText = '';
        
        if ($attackType === 'scout' || $attackType === 'spy') {
            $iconFile = 'scout.png';
            $altText = 'Scout Report';
        } elseif ($outcome === 'attacker_win' || strpos($outcome, 'win') !== false) {
            $iconFile = 'victory.png';
            $altText = 'Victory';
        } elseif ($outcome === 'defender_hold' || strpos($outcome, 'hold') !== false || strpos($outcome, 'defeat') !== false) {
            $iconFile = 'defeat.png';
            $altText = 'Defeat';
        } else {
            // Default to generic battle icon
            $iconFile = 'battle.png';
            $altText = 'Battle Report';
        }
        
        // Return img tag with 16x16 dimensions
        return sprintf(
            '<img src="/img/reports/%s" alt="%s" width="16" height="16" style="vertical-align: middle; margin-right: 5px;">',
            htmlspecialchars($iconFile),
            htmlspecialchars($altText)
        );
    }
    
    /**
     * Render troops table for battle report.
     * 
     * @param array $troops Troop data
     * @return string HTML table
     */
    private function renderTroopsTable(array $troops): string
    {
        $html = '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Unit</th>' . "\n";
        $html .= '<th>Attacker Sent</th>' . "\n";
        $html .= '<th>Attacker Lost</th>' . "\n";
        $html .= '<th>Attacker Survivors</th>' . "\n";
        $html .= '<th>Defender Present</th>' . "\n";
        $html .= '<th>Defender Lost</th>' . "\n";
        $html .= '<th>Defender Survivors</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        // Get all unit types
        $attackerSent = $troops['attacker_sent'] ?? [];
        $attackerLost = $troops['attacker_lost'] ?? [];
        $attackerSurvivors = $troops['attacker_survivors'] ?? [];
        $defenderPresent = $troops['defender_present'] ?? [];
        $defenderLost = $troops['defender_lost'] ?? [];
        $defenderSurvivors = $troops['defender_survivors'] ?? [];
        
        $allUnits = array_unique(array_merge(
            array_keys($attackerSent),
            array_keys($defenderPresent)
        ));
        
        foreach ($allUnits as $unitType) {
            $html .= '<tr>' . "\n";
            $html .= sprintf('<td>%s</td>' . "\n", htmlspecialchars(ucfirst($unitType)));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($attackerSent[$unitType] ?? 0));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($attackerLost[$unitType] ?? 0));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($attackerSurvivors[$unitType] ?? 0));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($defenderPresent[$unitType] ?? 0));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($defenderLost[$unitType] ?? 0));
            $html .= sprintf('<td>%d</td>' . "\n", (int)($defenderSurvivors[$unitType] ?? 0));
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        
        return $html;
    }

    /**
     * Format duration in seconds to human-readable string.
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration (e.g., "1h 30m 45s")
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%ds', $seconds);
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) {
            $parts[] = sprintf('%dh', $hours);
        }
        if ($minutes > 0) {
            $parts[] = sprintf('%dm', $minutes);
        }
        if ($secs > 0 || empty($parts)) {
            $parts[] = sprintf('%ds', $secs);
        }
        
        return implode(' ', $parts);
    }

    /**
     * Render queue display as timestamped text entries.
     * Helper method for displaying building/unit queues.
     * 
     * @param array $queueItems Array of queue items
     * @return string Formatted HTML
     */
    public function renderQueueDisplay(array $queueItems): string
    {
        if (empty($queueItems)) {
            return '<p>No items in queue</p>' . "\n";
        }
        
        $html = '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Item</th>' . "\n";
        $html .= '<th>Level/Quantity</th>' . "\n";
        $html .= '<th>Completion Time</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        foreach ($queueItems as $item) {
            $name = htmlspecialchars($item['name'] ?? 'Unknown');
            $level = isset($item['level']) ? sprintf('Level %d', (int)$item['level']) : sprintf('%d units', (int)($item['quantity'] ?? 0));
            $finishTime = $item['finish_time'] ?? null;
            $timeStr = $finishTime ? date('Y-m-d H:i:s', strtotime($finishTime)) : 'Unknown';
            
            $html .= '<tr>' . "\n";
            $html .= sprintf('<td>%s</td>' . "\n", $name);
            $html .= sprintf('<td>%s</td>' . "\n", $level);
            $html .= sprintf('<td>%s</td>' . "\n", $timeStr);
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        
        return $html;
    }

    /**
     * Render recruitment panel showing unit queues as text.
     * Implements Requirement 4.1: Display unit queues as "[Unit] ([Completed]/[Total] complete, [Time] remaining)"
     * 
     * @param array $queues Array of recruitment queue items
     * @return string Formatted HTML
     */
    public function renderRecruitmentQueues(array $queues): string
    {
        if (empty($queues)) {
            return '<p>No units in training queue</p>' . "\n";
        }
        
        $html = '<p><b>Training Queue:</b></p>' . "\n";
        
        foreach ($queues as $queue) {
            $unitName = htmlspecialchars($queue['unit_name'] ?? 'Unknown');
            $completed = (int)($queue['count_finished'] ?? 0);
            $total = (int)($queue['count'] ?? 0);
            $finishAt = (int)($queue['finish_at'] ?? 0);
            
            $remaining = max(0, $finishAt - time());
            $timeStr = $this->formatDuration($remaining);
            
            $html .= sprintf(
                '%s (%d/%d complete, %s remaining)<br>' . "\n",
                $unitName,
                $completed,
                $total,
                $timeStr
            );
        }
        
        return $html;
    }

    /**
     * Render recruitment costs display.
     * Implements Requirement 4.2: Display costs as "Cost: [Wood]W, [Clay]C, [Iron]I, [Pop] Pop, Time: [Duration]"
     * 
     * @param array $unit Unit data with costs
     * @param int $buildingLevel Building level for time calculation
     * @return string Formatted cost string
     */
    public function renderRecruitmentCosts(array $unit, int $buildingLevel): string
    {
        $wood = (int)($unit['cost_wood'] ?? 0);
        $clay = (int)($unit['cost_clay'] ?? 0);
        $iron = (int)($unit['cost_iron'] ?? 0);
        $pop = (int)($unit['population'] ?? 0);
        $time = (int)($unit['training_time'] ?? 0);
        
        return sprintf(
            'Cost: %dW, %dC, %dI, %d Pop, Time: %s',
            $wood,
            $clay,
            $iron,
            $pop,
            $this->formatDuration($time)
        );
    }

    /**
     * Render unit statistics comparison table.
     * Implements Requirement 4.3: Display comparison table with Attack/Defense columns
     * 
     * @param array $units Array of unit data
     * @return string HTML table
     */
    public function renderUnitStatsTable(array $units): string
    {
        if (empty($units)) {
            return '<p>No units available</p>' . "\n";
        }
        
        $html = '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Unit</th>' . "\n";
        $html .= '<th>Attack</th>' . "\n";
        $html .= '<th>Def (Inf)</th>' . "\n";
        $html .= '<th>Def (Cav)</th>' . "\n";
        $html .= '<th>Def (Rng)</th>' . "\n";
        $html .= '<th>Speed</th>' . "\n";
        $html .= '<th>Carry</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        foreach ($units as $unit) {
            $name = htmlspecialchars($unit['name'] ?? 'Unknown');
            $attack = (int)($unit['attack'] ?? 0);
            $defInf = (int)($unit['defense_infantry'] ?? $unit['defense'] ?? 0);
            $defCav = (int)($unit['defense_cavalry'] ?? 0);
            $defRng = (int)($unit['defense_ranged'] ?? $unit['defense_archer'] ?? 0);
            $speed = (int)($unit['speed'] ?? $unit['speed_min_per_field'] ?? 0);
            $carry = (int)($unit['carry_capacity'] ?? 0);
            
            $html .= '<tr>' . "\n";
            $html .= sprintf('<td>%s</td>' . "\n", $name);
            $html .= sprintf('<td>%d</td>' . "\n", $attack);
            $html .= sprintf('<td>%d</td>' . "\n", $defInf);
            $html .= sprintf('<td>%d</td>' . "\n", $defCav);
            $html .= sprintf('<td>%d</td>' . "\n", $defRng);
            $html .= sprintf('<td>%d</td>' . "\n", $speed);
            $html .= sprintf('<td>%d</td>' . "\n", $carry);
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        
        return $html;
    }

    /**
     * Render recruitment form with quantity inputs and recruit buttons.
     * Implements Requirement 4.4: Quantity input boxes and "Recruit" buttons (no drag-and-drop)
     * 
     * @param array $units Array of available units
     * @param int $villageId Village ID
     * @param string $buildingType Building type (barracks, stable, workshop)
     * @param int $buildingLevel Building level
     * @return string HTML form
     */
    public function renderRecruitmentForm(array $units, int $villageId, string $buildingType, int $buildingLevel): string
    {
        if (empty($units)) {
            return '<p>No units available for recruitment</p>' . "\n";
        }
        
        $html = '<form method="post" action="/units/recruit_units.php">' . "\n";
        $html .= sprintf('<input type="hidden" name="village_id" value="%d">' . "\n", $villageId);
        $html .= sprintf('<input type="hidden" name="building_type" value="%s">' . "\n", htmlspecialchars($buildingType));
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">' . "\n";
        
        $html .= '<table border="1" cellpadding="2" cellspacing="0" width="100%">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Unit</th>' . "\n";
        $html .= '<th>Cost</th>' . "\n";
        $html .= '<th>Stats</th>' . "\n";
        $html .= '<th>Quantity</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        foreach ($units as $unit) {
            $unitId = (int)($unit['id'] ?? 0);
            $name = htmlspecialchars($unit['name'] ?? 'Unknown');
            
            // Calculate training time for this building level
            $trainingTime = (int)($unit['training_time_base'] ?? 0);
            if ($buildingLevel > 1) {
                $trainingTime = (int)floor($trainingTime * pow(0.95, $buildingLevel - 1));
            }
            $unit['training_time'] = $trainingTime;
            
            $costStr = $this->renderRecruitmentCosts($unit, $buildingLevel);
            
            $attack = (int)($unit['attack'] ?? 0);
            $defInf = (int)($unit['defense_infantry'] ?? $unit['defense'] ?? 0);
            $defCav = (int)($unit['defense_cavalry'] ?? 0);
            $defRng = (int)($unit['defense_ranged'] ?? $unit['defense_archer'] ?? 0);
            
            $statsStr = sprintf(
                'Atk:%d, Def:%d/%d/%d',
                $attack,
                $defInf,
                $defCav,
                $defRng
            );
            
            $html .= '<tr>' . "\n";
            $html .= sprintf('<td><b>%s</b></td>' . "\n", $name);
            $html .= sprintf('<td>%s</td>' . "\n", $costStr);
            $html .= sprintf('<td>%s</td>' . "\n", $statsStr);
            $html .= sprintf(
                '<td><input type="number" name="recruit[%d]" min="0" value="0" size="5"></td>' . "\n",
                $unitId
            );
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        $html .= '<p><input type="submit" value="Recruit selected units"></p>' . "\n";
        $html .= '</form>' . "\n";
        
        return $html;
    }
}


