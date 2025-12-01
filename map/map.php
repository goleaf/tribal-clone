<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . 'VillageManager.php';
require_once '../config/config.php';
require_once '../lib/Database.php';

// Access control - only for logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch the user's first village
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $village_id ? $villageManager->getVillageInfo($village_id) : null;

// Establish the starting position (map center or the player's village)
$x = isset($_GET['x']) ? (int)$_GET['x'] : 50;
$y = isset($_GET['y']) ? (int)$_GET['y'] : 50;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;
// Map radius should align with the rendered grid: 2 * radius + 1 = size
$radius = max(3, min(20, (int)floor(($size - 1) / 2)));

// Map view parameters (from GET or defaults)
$center_x = isset($_GET['x']) ? (int)$_GET['x'] : 0;
$center_y = isset($_GET['y']) ? (int)$_GET['y'] : 0;

// If we have a village, use its coordinates as the default map center
if ($village_id) {
    $village = $villageManager->getVillageInfo($village_id);
    if ($village) {
        // If GET coordinates were not provided, use the village coordinates
        if (!isset($_GET['x'])) {
            $center_x = $village['x_coord'];
        }
        if (!isset($_GET['y'])) {
            $center_y = $village['y_coord'];
        }
    }
}

// Fetch villages within range
$stmt = $conn->prepare("
    SELECT v.id, v.name, v.x_coord, v.y_coord, v.user_id, u.username
    FROM villages v
    LEFT JOIN users u ON v.user_id = u.id
    WHERE 
        v.world_id = ? AND
        v.x_coord BETWEEN ? AND ? AND 
        v.y_coord BETWEEN ? AND ?
    ORDER BY v.y_coord ASC, v.x_coord ASC
");

$world_id = CURRENT_WORLD_ID;
$min_x = $center_x - $radius;
$max_x = $center_x + $radius;
$min_y = $center_y - $radius;
$max_y = $center_y + $radius;

$stmt->bind_param("iiiii", $world_id, $min_x, $max_x, $min_y, $max_y);
$stmt->execute();
$result = $stmt->get_result();

// Build a map of villages keyed by coordinates
$villages_map = [];
while ($row = $result->fetch_assoc()) {
    $villages_map[$row['y_coord']][$row['x_coord']] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'owner' => $row['username'] ?? 'Unoccupied village',
        'user_id' => $row['user_id'],
        'is_own' => ($row['user_id'] == $user_id)
    ];
}

// AJAX payload for client-side re-centering without full page reload
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'center' => ['x' => $center_x, 'y' => $center_y],
        'size' => $size,
        'villages' => $villages_map,
        'player_village' => $village ? [
            'id' => $village['id'],
            'x' => $village['x_coord'],
            'y' => $village['y_coord'],
            'name' => $village['name']
        ] : null
    ]);
    exit;
}

$pageTitle = 'World Map';
require '../header.php';
?>

<div id="game-container" class="map-page">
    <header id="main-header" class="map-header">
        <div class="header-title">
            <span class="game-logo"><img src="../img/ds_graphic/world.png" alt="World" /></span>
            <div>
                <div class="eyebrow">World Map</div>
                <div class="title">Frontier</div>
            </div>
        </div>
        <div class="header-user">
            <div class="player-name"><?= htmlspecialchars($username) ?></div>
            <?php if ($village): ?>
                <div class="village-name-display" data-village-id="<?= $village['id'] ?>">
                    <?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main id="main-content" class="map-main">
        <section class="map-toolbar">
            <div class="coords-block">
                <div class="label">Center</div>
                <div class="value"><?= $center_x ?>|<?= $center_y ?></div>
            </div>
            <div class="toolbar-controls">
                <div class="dpad">
                    <button class="nav-btn" data-dx="0" data-dy="-1" title="North"><img src="../img/map/map_n.png" alt="N"></button>
                    <button class="nav-btn" data-dx="-1" data-dy="0" title="West"><img src="../img/map/map_w.png" alt="W"></button>
                    <button class="nav-btn home-btn" data-home="1" title="Center on own village"><img src="../img/map/pointer_home.png" alt="Home"></button>
                    <button class="nav-btn" data-dx="1" data-dy="0" title="East"><img src="../img/map/map_e.png" alt="E"></button>
                    <button class="nav-btn" data-dx="0" data-dy="1" title="South"><img src="../img/map/map_s.png" alt="S"></button>
                </div>
                <div class="size-control">
                    <label for="map-size">Size</label>
                    <input type="range" id="map-size" min="7" max="31" step="2" value="<?php echo $size; ?>">
                    <span id="map-size-label"><?php echo $size; ?>x<?php echo $size; ?></span>
                </div>
                <form class="jump-form" onsubmit="return jumpToCoords(event);">
                    <label for="jump-x">Go to</label>
                    <input type="number" id="jump-x" name="x" placeholder="x" required>
                    <input type="number" id="jump-y" name="y" placeholder="y" required>
                    <button type="submit" class="btn-pill">Jump</button>
                </form>
            </div>
        </section>

        <div class="map-wrapper">
            <div class="map-grid-shell">
                <div id="map-grid" class="map-grid"></div>
            </div>
            <aside class="map-legend">
                <h4>Legend</h4>
                <div class="legend-row"><img src="../img/map/map_v6.png" alt="Own"> <span>Your village</span></div>
                <div class="legend-row"><img src="../img/map/map_v4.png" alt="Player"> <span>Player village</span></div>
                <div class="legend-row"><img src="../img/map/map_v2.png" alt="Barbarian"> <span>Barbarian village</span></div>
                <div class="legend-row"><span class="legend-chip">Empty</span> <span>Unsettled lands</span></div>
            </aside>
        </div>

        <!-- Popup for map tile details -->
        <div id="map-popup" class="map-popup" style="display: none;">
            <button class="popup-close-btn">&times;</button>
            <div class="popup-body">
                <div class="pill-row">
                    <span class="pill coords-pill" id="popup-village-coords"></span>
                    <span class="pill owner-pill" id="popup-village-owner"></span>
                </div>
                <h4 id="popup-village-name"></h4>
                <div class="popup-actions">
                    <button id="popup-send-units" class="btn-primary">Send units</button>
                    <a id="popup-open-village" class="btn-ghost" href="#">Open</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Pass village data to JavaScript
let villagesData = <?php echo json_encode($villages_map); ?>;
const currentVillageId = <?php echo $village_id ?? 'null'; ?>;
let centerCoords = { x: <?php echo $center_x; ?>, y: <?php echo $center_y; ?> };
let mapSize = <?php echo $size; ?>;
const grassTiles = ['../img/map/gras1.png', '../img/map/gras2.png', '../img/map/gras3.png', '../img/map/gras4.png'];
const iconOwn = '../img/map/map_v6.png';
const iconPlayer = '../img/map/map_v4.png';
const iconBarb = '../img/map/map_v2.png';
const iconEmpty = '../img/map/empty.png';
const ajaxEndpoint = 'map.php';
const grassTileCount = grassTiles.length;

function getGrassTile(x, y) {
    // Deterministic hash to keep tile textures stable across re-renders
    const hash = Math.abs((x * 73856093) ^ (y * 19349663));
    return grassTiles[hash % grassTileCount];
}

document.addEventListener('DOMContentLoaded', () => {
    renderMap(villagesData, centerCoords, mapSize, currentVillageId);
    updateCenterDisplay();

    // Add event listeners for map controls
    document.querySelectorAll('.nav-btn').forEach(btn => {
        const dx = parseInt(btn.dataset.dx || 0, 10);
        const dy = parseInt(btn.dataset.dy || 0, 10);
        if (!isNaN(dx) || !isNaN(dy)) {
            btn.addEventListener('click', () => moveMap(dx, dy));
        }
        if (btn.dataset.home) {
            btn.addEventListener('click', () => jumpToCenter());
        }
    });
    const sizeInput = document.getElementById('map-size');
    const sizeLabel = document.getElementById('map-size-label');
    sizeInput.addEventListener('input', () => {
        sizeLabel.textContent = `${sizeInput.value}x${sizeInput.value}`;
    });
    sizeInput.addEventListener('change', () => resizeMap());

    // Event delegation for map tiles
    document.getElementById('map-grid').addEventListener('click', function(event) {
        const tile = event.target.closest('.map-tile');
        // Only proceed when the dataset attributes exist (avoid false negatives from incorrect boolean logic)
        if (tile && tile.dataset.x !== undefined && tile.dataset.y !== undefined) {
            const x = parseInt(tile.dataset.x, 10);
            const y = parseInt(tile.dataset.y, 10);
            showVillagePopup(x, y);
        }
    });

    // Close popup button
    const popupCloseBtn = document.querySelector('#map-popup .popup-close-btn');
    if (popupCloseBtn) {
        popupCloseBtn.addEventListener('click', hideVillagePopup);
    }

    // Send units button
    const popupSendUnits = document.getElementById('popup-send-units');
    if (popupSendUnits) {
        popupSendUnits.addEventListener('click', function() {
            const targetVillageId = this.dataset.villageId;
            if (targetVillageId && currentVillageId) {
                fetch(`../combat/attack.php?target_village_id=${targetVillageId}&source_village_id=${currentVillageId}&ajax=1`)
                    .then(response => response.text())
                    .then(html => {
                        const modalContent = document.getElementById('generic-modal-content');
                        const modal = document.getElementById('generic-modal');
                        if (modalContent && modal) {
                            modalContent.innerHTML = html;
                            modal.style.display = 'block';
                        }
                        hideVillagePopup();
                    })
                    .catch(error => console.error('Error loading attack form:', error));
            }
        });
    }

    // Generic modal close logic
    const genericModal = document.getElementById('generic-modal');
    if (genericModal) {
        const closeModalButton = genericModal.querySelector('.close-button');
        if (closeModalButton) {
            closeModalButton.addEventListener('click', () => {
                genericModal.style.display = 'none';
            });
        }

        window.addEventListener('click', (event) => {
            if (event.target === genericModal) {
                genericModal.style.display = 'none';
            }
        });
    }

    // Handle clicks outside the popup to close it
    document.addEventListener('click', function(event) {
        const popup = document.getElementById('map-popup');
        const isClickInsidePopup = popup.contains(event.target);
        const isClickOnTile = event.target.closest('.map-tile');
        
        // Close popup if clicked outside the popup AND not on a map tile
        if (!isClickInsidePopup && !isClickOnTile && popup.style.display !== 'none') {
            hideVillagePopup();
        }
    });

    // Prevent clicks inside the popup from closing it via the document listener
    document.getElementById('map-popup').addEventListener('click', function(event) {
        event.stopPropagation();
    });
});

function renderMap(villages, center, size, currentVillageId) {
    const mapGrid = document.getElementById('map-grid');
    mapGrid.innerHTML = ''; // Clear existing map
    const radius = Math.floor((size - 1) / 2);
    const startX = center.x - radius;
    const startY = center.y - radius;
    const tileSize = 52;
    const fragment = document.createDocumentFragment();

    for (let y = 0; y < size; y++) {
        const coordY = startY + y;
        for (let x = 0; x < size; x++) {
            const coordX = startX + x;
            const village = villages[coordY] ? villages[coordY][coordX] : null;
            const tile = document.createElement('div');
            tile.classList.add('map-tile');
            tile.dataset.x = coordX;
            tile.dataset.y = coordY;

            const ground = getGrassTile(coordX, coordY);
            tile.style.backgroundImage = `url(${ground})`;

            let iconSrc = iconEmpty;
            let badge = '';

            if (village) {
                if (village.is_own) {
                    iconSrc = iconOwn;
                    tile.classList.add('own-village');
                    badge = '<span class="badge badge-own">Own</span>';
                } else if (village.user_id === null) {
                    iconSrc = iconBarb;
                    tile.classList.add('barbarian');
                    badge = '<span class="badge badge-barb">Barb</span>';
                } else {
                    iconSrc = iconPlayer;
                    tile.classList.add('player');
                    badge = `<span class="badge badge-player">${village.owner ?? 'Player'}</span>`;
                }
            } else {
                tile.classList.add('empty');
            }

            tile.innerHTML = `
                ${badge}
                <img class="tile-icon" src="${iconSrc}" alt="tile icon">
                <div class="coords">${coordX}|${coordY}</div>
            `;
            fragment.appendChild(tile);
        }
    }
     // Adjust grid columns based on size
     mapGrid.style.gridTemplateColumns = `repeat(${size}, ${tileSize}px)`;
     mapGrid.style.gridTemplateRows = `repeat(${size}, ${tileSize}px)`;
     mapGrid.appendChild(fragment);
}

function updateCenterDisplay() {
    const centerDisplay = document.querySelector('.coords-block .value');
    if (centerDisplay) {
        centerDisplay.textContent = `${centerCoords.x}|${centerCoords.y}`;
    }
}

function updateUrl(x, y, size) {
    const params = new URLSearchParams(window.location.search);
    params.set('x', x);
    params.set('y', y);
    params.set('size', size);
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.replaceState({}, '', newUrl);
}

async function fetchMapData(targetX, targetY, targetSize) {
    const url = `${ajaxEndpoint}?x=${encodeURIComponent(targetX)}&y=${encodeURIComponent(targetY)}&size=${encodeURIComponent(targetSize)}&ajax=1`;
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error(`Map fetch failed with status ${response.status}`);
    }
    return response.json();
}

async function applyMapUpdate(targetX, targetY, targetSize) {
    try {
        const data = await fetchMapData(targetX, targetY, targetSize);
        centerCoords = { x: data.center.x, y: data.center.y };
        mapSize = data.size;
        villagesData = data.villages;
        renderMap(villagesData, centerCoords, mapSize, currentVillageId);
        updateCenterDisplay();

        const sizeInput = document.getElementById('map-size');
        const sizeLabel = document.getElementById('map-size-label');
        if (sizeInput) sizeInput.value = mapSize;
        if (sizeLabel) sizeLabel.textContent = `${mapSize}x${mapSize}`;

        updateUrl(centerCoords.x, centerCoords.y, mapSize);
    } catch (error) {
        console.error('Falling back to full reload after map fetch failure:', error);
        window.location.href = `map.php?x=${targetX}&y=${targetY}&size=${targetSize}`;
    }
}

function moveMap(dx, dy) {
    const currentX = centerCoords.x;
    const currentY = centerCoords.y;
    applyMapUpdate(currentX + dx * mapSize, currentY + dy * mapSize, mapSize);
}

function jumpToCenter() {
    <?php if ($village): ?>
    const homeX = <?php echo $village['x_coord']; ?>;
    const homeY = <?php echo $village['y_coord']; ?>;
    applyMapUpdate(homeX, homeY, mapSize);
    <?php else: ?>
    console.error('No village to center on.');
    <?php endif; ?>
}

function resizeMap() {
     const newSize = parseInt(document.getElementById('map-size').value);
     if (!isNaN(newSize) && newSize >= 7 && newSize <= 31) {
          applyMapUpdate(centerCoords.x, centerCoords.y, newSize);
     } else {
          alert('Map size must be a number between 7 and 31.');
     }
}

function jumpToCoords(event) {
    event.preventDefault();
    const xInput = document.getElementById('jump-x');
    const yInput = document.getElementById('jump-y');
    const targetX = parseInt(xInput.value, 10);
    const targetY = parseInt(yInput.value, 10);
    if (Number.isFinite(targetX) && Number.isFinite(targetY)) {
        applyMapUpdate(targetX, targetY, mapSize);
    }
    return false;
}

function showVillagePopup(x, y) {
    const village = villagesData[y] ? villagesData[y][x] : null;
    const popup = document.getElementById('map-popup');
    const popupVillageName = document.getElementById('popup-village-name');
    const popupVillageOwner = document.getElementById('popup-village-owner');
    const popupVillageCoords = document.getElementById('popup-village-coords');
    const popupSendUnitsButton = document.getElementById('popup-send-units');
    const popupOpenVillage = document.getElementById('popup-open-village');
    
    if (village) {
        popupVillageName.textContent = village.name;
        popupVillageOwner.textContent = village.owner;
        popupVillageCoords.textContent = `${x}|${y}`;
        
        // Set village ID for buttons
        popupSendUnitsButton.dataset.villageId = village.id;
        if (village.user_id) {
            popupOpenVillage.style.display = 'inline-block';
            popupOpenVillage.href = `../player/player.php?id=${village.user_id}`;
        } else {
            popupOpenVillage.style.display = 'none';
        }

        // Show/hide/enable/disable buttons based on village type/ownership
        if (village.is_own) {
            popupSendUnitsButton.style.display = 'none'; // Can't attack own village
        } else {
             popupSendUnitsButton.style.display = 'block';
             // Maybe disable attack button if not enough units/conditions not met
             // For now, always enabled for non-own villages
        }
        
        // Position the popup near the tile
        const tileElement = document.querySelector(`.map-tile[data-x="${x}"][data-y="${y}"]`);
        if (tileElement) {
             const tileRect = tileElement.getBoundingClientRect();
             const gameContainer = document.getElementById('game-container'); // Assuming game-container is the scrollable parent
             const containerRect = gameContainer.getBoundingClientRect();

             // Calculate position relative to the game-container (or viewport if container is not relative)
             let popupLeft = tileRect.right + 10; // 10px right of the tile
             let popupTop = tileRect.top + tileRect.height / 2; // Vertically centered with tile
             
             // Adjust position if near the right edge of the viewport
             if (popupLeft + popup.offsetWidth > window.innerWidth - 20) { // 20px margin from right edge
                 popupLeft = tileRect.left - popup.offsetWidth - 10; // Position to the left of the tile
             }

              // Adjust position relative to the top of the game container if game-container has position: relative
              // Otherwise, position relative to viewport
              // Assuming game-container has position: relative
              popup.style.left = `${popupLeft - containerRect.left}px`;
              popup.style.top = `${popupTop - containerRect.top - popup.offsetHeight / 2}px`;

             popup.style.display = 'block';
        }

    } else {
        // Handle empty tile click - maybe show different info or do nothing
        hideVillagePopup();
    }
}

function hideVillagePopup() {
    document.getElementById('map-popup').style.display = 'none';
}

function jumpToCoords(event) {
    event.preventDefault();
    const xInput = document.getElementById('jump-x');
    const yInput = document.getElementById('jump-y');
    const currentSize = parseInt(document.getElementById('map-size').value, 10) || mapSize;
    const xVal = parseInt(xInput.value, 10);
    const yVal = parseInt(yInput.value, 10);
    if (isNaN(xVal) || isNaN(yVal)) {
        alert('Enter valid coordinates.');
        return false;
    }
    window.location.href = `map.php?x=${xVal}&y=${yVal}&size=${currentSize}`;
    return false;
}
</script>

<style>
:root {
    --map-bg: radial-gradient(circle at 20% 20%, #f3e3c2, #e4cfa0 60%, #d6bb83);
    --tile-size: 52px;
    --tile-radius: 10px;
    --panel-bg: rgba(255, 255, 255, 0.85);
    --panel-border: #d2b17a;
    --accent: #a26a31;
    --accent-strong: #8d4c1f;
}

.map-page {
    background: var(--map-bg);
}

.map-header .game-logo img {
    width: 28px;
    height: 28px;
}

.map-header .eyebrow {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #8d5c2c;
}

.map-header .title {
    font-size: 20px;
    font-weight: 700;
    color: #4a2c0f;
}

.map-main {
    padding: 12px 16px 24px;
}

.map-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: 12px;
    padding: 12px 16px;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 14px;
}

.coords-block .label {
    font-size: 11px;
    text-transform: uppercase;
    color: #8d5c2c;
    letter-spacing: 0.08em;
}

.coords-block .value {
    font-size: 18px;
    font-weight: 700;
    color: #2d1a07;
}

.toolbar-controls {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.dpad {
    display: grid;
    grid-template-columns: repeat(3, 40px);
    grid-template-rows: repeat(2, 40px);
    gap: 6px;
    align-items: center;
    justify-items: center;
}

.nav-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--panel-border);
    border-radius: 10px;
    background: linear-gradient(135deg, #fff8ec, #f0d8ad);
    cursor: pointer;
    box-shadow: 0 6px 12px rgba(0,0,0,0.08);
    transition: transform 120ms ease, box-shadow 120ms ease;
}

.nav-btn img {
    width: 22px;
    height: 22px;
}

.nav-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 14px rgba(0,0,0,0.1);
}

.home-btn {
    grid-column: 2;
    grid-row: 1 / span 2;
    background: linear-gradient(135deg, #ffe8c7, #f2c581);
}

.size-control {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #3b2410;
}

.size-control input[type="range"] {
    accent-color: var(--accent);
}

.jump-form {
    display: flex;
    align-items: center;
    gap: 6px;
}

.jump-form input {
    width: 70px;
    padding: 6px 8px;
    border: 1px solid var(--panel-border);
    border-radius: 8px;
    background: #fffdfa;
}

.btn-pill {
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid var(--accent);
    background: linear-gradient(135deg, #f7d8aa, #e7b36f);
    color: #3b2410;
    font-weight: 700;
    cursor: pointer;
    transition: transform 120ms ease, box-shadow 120ms ease;
}

.btn-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 14px rgba(0,0,0,0.1);
}

.map-wrapper {
    display: grid;
    grid-template-columns: 1fr 240px;
    gap: 12px;
    align-items: start;
}

.map-grid-shell {
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 18px 32px rgba(0,0,0,0.08);
    overflow: auto;
}

.map-grid {
    display: grid;
    gap: 2px;
}

.map-tile {
    position: relative;
    width: var(--tile-size);
    height: var(--tile-size);
    border-radius: var(--tile-radius);
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06);
    background-size: cover;
    background-position: center;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: transform 80ms ease, box-shadow 80ms ease;
}

.map-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 14px rgba(0,0,0,0.12);
}

.map-tile .tile-icon {
    width: 32px;
    height: 32px;
    filter: drop-shadow(0 4px 4px rgba(0,0,0,0.28));
}

.map-tile .coords {
    position: absolute;
    bottom: 4px;
    right: 6px;
    background: rgba(0,0,0,0.55);
    color: #fff;
    font-size: 10px;
    padding: 2px 4px;
    border-radius: 6px;
}

.badge {
    position: absolute;
    top: 5px;
    left: 5px;
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 10px;
    color: #fff;
    font-weight: 700;
    text-shadow: 0 1px 1px rgba(0,0,0,0.25);
}

.badge-own { background: #2f9d64; }
.badge-player { background: #2d6cdf; }
.badge-barb { background: #6d5438; }

.map-tile.own-village { box-shadow: inset 0 0 0 2px rgba(47,157,100,0.6); }
.map-tile.player { box-shadow: inset 0 0 0 2px rgba(45,108,223,0.5); }
.map-tile.barbarian { box-shadow: inset 0 0 0 2px rgba(109,84,56,0.6); }
.map-tile.empty { opacity: 0.9; }

.map-legend {
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 14px 22px rgba(0,0,0,0.08);
}

.map-legend h4 {
    margin: 0 0 8px 0;
    font-size: 15px;
    color: #3b2410;
}

.legend-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 13px;
}

.legend-row img {
    width: 24px;
    height: 24px;
}

.legend-chip {
    display: inline-block;
    min-width: 24px;
    min-height: 24px;
    border-radius: 8px;
    background: #d6c6a4;
    border: 1px solid #b9a06c;
}

.map-popup {
    position: absolute;
    background: #fffdf8;
    border: 1px solid var(--panel-border);
    border-radius: 12px;
    padding: 10px 12px;
    box-shadow: 0 18px 26px rgba(0,0,0,0.16);
    z-index: 20;
    min-width: 220px;
}

.popup-close-btn {
    position: absolute;
    top: 6px;
    right: 8px;
    border: none;
    background: transparent;
    font-size: 18px;
    cursor: pointer;
    color: #8d5c2c;
}

.pill-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.pill {
    padding: 4px 8px;
    border-radius: 999px;
    background: #f2dec0;
    color: #3b2410;
    font-size: 12px;
    border: 1px solid #d6b07a;
}

.owner-pill { background: #e6f2ff; border-color: #aac6f1; }
.coords-pill { background: #e7f6e8; border-color: #b6dfc1; }

.popup-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.btn-primary, .btn-ghost {
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid var(--accent);
    cursor: pointer;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
}

.btn-primary {
    background: linear-gradient(135deg, #f7d8aa, #e7b36f);
    color: #3b2410;
}

.btn-ghost {
    background: transparent;
    color: var(--accent-strong);
}

@media (max-width: 1000px) {
    .map-wrapper {
        grid-template-columns: 1fr;
    }
    .map-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<?php require '../footer.php'; ?> 
