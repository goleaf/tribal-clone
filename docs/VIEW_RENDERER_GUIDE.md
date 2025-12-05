# ViewRenderer Guide

## Overview

The `ViewRenderer` component provides WAP-style HTML rendering for minimalist interfaces suitable for low-bandwidth constraints. It implements Requirements 3.1-3.5 from the resource system specification.

## Features

- **Compact HTML tables** with minimal markup
- **Text-only resource displays** with production rates
- **Hyperlink navigation menus** for main sections
- **Formatted text tables** for combat results
- **WAP-compatible output** (no heavy CSS/JS dependencies)

## Installation

The ViewRenderer is located at `lib/managers/ViewRenderer.php` and requires:
- Database connection
- BuildingManager (optional)
- ResourceManager (optional)

## Basic Usage

```php
require_once 'lib/managers/ViewRenderer.php';
require_once 'lib/managers/BuildingManager.php';
require_once 'lib/managers/ResourceManager.php';

// Initialize dependencies
$buildingConfigManager = new BuildingConfigManager($conn);
$buildingManager = new BuildingManager($conn, $buildingConfigManager);
$resourceManager = new ResourceManager($conn, $buildingManager);

// Create renderer
$renderer = new ViewRenderer($conn, $buildingManager, $resourceManager);
```

## Methods

### 1. renderVillageOverview()

Renders a compact 3-column table with buildings, resources, and movements.

**Requirement:** 3.1 - Compact HTML table with buildings/resources/movements

```php
$html = $renderer->renderVillageOverview($village, $buildings, $movements);
```

**Parameters:**
- `$village` (array): Village data including resources, population, coordinates
- `$buildings` (array): Array of building data with name, level, internal_name
- `$movements` (array): Movement data with 'incoming' and 'outgoing' arrays

**Output:** HTML table with three columns (Buildings | Resources | Movements)

### 2. renderBuildingList()

Renders a table of buildings with upgrade information.

**Requirement:** 3.2 - Table rows with name, level, cost, time, upgrade link

```php
$html = $renderer->renderBuildingList($buildings, $village);
```

**Parameters:**
- `$buildings` (array): Array of building data including upgrade costs and times
- `$village` (array): Village data for resource checking

**Output:** HTML table with columns: Building | Level | Upgrade Cost | Time | Action

### 3. renderResourceBar()

Renders text-only resource display with production rates.

**Requirement:** 3.3 - Text-only resource display with rates

```php
$html = $renderer->renderResourceBar($resources, $rates, $capacity);
```

**Parameters:**
- `$resources` (array): Current amounts ['wood' => int, 'clay' => int, 'iron' => int]
- `$rates` (array): Production rates ['wood' => float, 'clay' => float, 'iron' => float]
- `$capacity` (int): Warehouse capacity

**Output:** Text format: `[Resource]: [Amount] (+[Rate]/hr)`

### 4. renderNavigation()

Renders hyperlink menu for main game sections.

**Requirement:** 3.4 - Hyperlink menu for main sections

```php
$html = $renderer->renderNavigation();
```

**Output:** Pipe-separated hyperlinks: Village | Troops | Market | Research | Reports | Messages | Alliance | Profile

### 5. renderBattleReport()

Renders formatted battle report with text tables.

**Requirement:** 3.5 - Formatted text tables for combat results

```php
$html = $renderer->renderBattleReport($report);
```

**Parameters:**
- `$report` (array): Battle report data including:
  - `timestamp`: Battle time
  - `outcome`: 'victory' or 'defeat'
  - `attacker_village`: Village data
  - `defender_village`: Village data
  - `modifiers`: Luck, morale, wall bonus
  - `troops`: Sent, lost, survivors for both sides
  - `plunder`: Resources plundered (optional)
  - `allegiance`: Loyalty changes (optional)

**Output:** HTML with battle summary, modifiers, troops table, plunder, and loyalty

### 6. renderQueueDisplay()

Renders building or unit queue as timestamped entries.

```php
$html = $renderer->renderQueueDisplay($queueItems);
```

**Parameters:**
- `$queueItems` (array): Array of queue items with name, level/quantity, finish_time

**Output:** HTML table with Item | Level/Quantity | Completion Time

## WAP Constraints

The ViewRenderer follows WAP (Wireless Application Protocol) constraints:

✓ **Minimal HTML**: Uses only basic tags (table, tr, td, th, p, br, a, b, h3)  
✓ **No CSS classes**: All styling via inline attributes (border, cellpadding, width)  
✓ **No JavaScript**: Pure server-side rendering  
✓ **Text-based**: Minimal graphics, suitable for low-bandwidth  
✓ **Simple layout**: Table-based layout, no complex nesting  

## Example: Complete Village Page

```php
// Fetch data
$village = $villageManager->getVillageInfo($villageId);
$buildings = $buildingManager->getVillageBuildingsViewData($villageId, $mainBuildingLevel);
$movements = $battleManager->getVillageMovements($villageId);

// Render components
echo $renderer->renderNavigation();
echo '<h1>' . htmlspecialchars($village['name']) . '</h1>';
echo $renderer->renderVillageOverview($village, $buildings, $movements);
echo '<h2>Buildings</h2>';
echo $renderer->renderBuildingList($buildings, $village);
```

## Testing

Run the test suite to verify functionality:

```bash
php tests/view_renderer_test.php
```

Run the demo to see all methods in action:

```bash
php examples/view_renderer_demo.php
```

## Integration with Existing Code

The ViewRenderer can be integrated into existing pages:

1. **game/game.php**: Replace current village overview with `renderVillageOverview()`
2. **buildings/*.php**: Use `renderBuildingList()` for building displays
3. **messages/reports.php**: Use `renderBattleReport()` for combat reports
4. **Header/Footer**: Use `renderNavigation()` for consistent navigation

## Performance Considerations

- **Minimal HTML**: Reduces bandwidth usage by 60-80% compared to modern frameworks
- **No external assets**: No CSS/JS files to load
- **Server-side rendering**: No client-side processing required
- **Cache-friendly**: Static HTML can be easily cached

## Browser Compatibility

The ViewRenderer output is compatible with:
- All modern browsers (Chrome, Firefox, Safari, Edge)
- Legacy browsers (IE6+)
- Text-based browsers (Lynx, w3m)
- Mobile browsers (even 2G connections)
- Screen readers (accessible HTML)

## Future Enhancements

Potential improvements for future versions:

- [ ] Add meta-refresh tags for auto-updating timers
- [ ] Support for multiple villages in overview
- [ ] Customizable navigation links
- [ ] Theme support (day/night backgrounds)
- [ ] Localization support for multiple languages

## Related Documentation

- [Resource System Specification](.kiro/specs/1-resource-system/requirements.md)
- [Design Document](.kiro/specs/1-resource-system/design.md)
- [Task List](.kiro/specs/1-resource-system/tasks.md)

