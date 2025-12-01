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
$username = $_SESSION['username'] ?? '';

// Fetch the user's first village
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $village_id ? $villageManager->getVillageInfo($village_id) : null;

$defaultX = $village['x_coord'] ?? 0;
$defaultY = $village['y_coord'] ?? 0;

$center_x = isset($_GET['x']) ? (int)$_GET['x'] : $defaultX;
$center_y = isset($_GET['y']) ? (int)$_GET['y'] : $defaultY;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;

$pageTitle = 'World Map';
require '../header.php';
?>

<div id="game-container" class="map-page">
    <header id="main-header" class="map-header">
        <div class="header-title">
            <span class="game-logo"><img src="../img/ds_graphic/world.png" alt="World" /></span>
            <div>
                <div class="eyebrow">World Map</div>
                <div class="title">Classic View</div>
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
                <div class="value" id="toolbar-center"><?= $center_x ?>|<?= $center_y ?></div>
            </div>
            <div class="toolbar-controls">
                <div class="dpad">
                    <button class="nav-btn" data-dx="0" data-dy="-1" title="North"><img src="../img/tw_map/map_n.png" alt="N"></button>
                    <button class="nav-btn" data-dx="-1" data-dy="0" title="West"><img src="../img/tw_map/map_w.png" alt="W"></button>
                    <button class="nav-btn home-btn" data-home="1" title="Center on own village"><img src="../img/tw_map/map_center.png" alt="Home"></button>
                    <button class="nav-btn" data-dx="1" data-dy="0" title="East"><img src="../img/tw_map/map_e.png" alt="E"></button>
                    <button class="nav-btn" data-dx="0" data-dy="1" title="South"><img src="../img/tw_map/map_s.png" alt="S"></button>
                </div>
                <div class="size-control">
                    <label for="map-size">Size</label>
                    <input type="range" id="map-size" min="7" max="31" step="2" value="<?php echo $size; ?>">
                    <span id="map-size-label"><?php echo $size; ?>x<?php echo $size; ?></span>
                </div>
                <form class="jump-form" id="jump-form">
                    <label for="jump-x">Go to</label>
                    <input type="number" id="jump-x" name="x" placeholder="x" required>
                    <input type="number" id="jump-y" name="y" placeholder="y" required>
                    <button type="submit" class="btn-pill">Jump</button>
                </form>
            </div>
        </section>

        <div class="map-wrapper">
            <div class="map-grid-shell">
                <div id="map-grid" class="map-grid" role="grid" aria-label="World map"></div>
            </div>
            <aside class="map-legend">
                <h4>Legend</h4>
                <div class="legend-row"><img src="../img/tw_map/v6.png" alt="Own"> <span>Your village</span></div>
                <div class="legend-row"><img src="../img/tw_map/v4.png" alt="Player"> <span>Player village</span></div>
                <div class="legend-row"><img src="../img/tw_map/b3.png" alt="Barbarian"> <span>Barbarian village</span></div>
                <div class="legend-row"><img src="../img/tw_map/gras2.png" alt="Empty"> <span>Unsettled lands</span></div>
            </aside>
        </div>

        <div id="map-popup" class="map-popup" style="display: none;">
            <button class="popup-close-btn" aria-label="Close">&times;</button>
            <div class="popup-body">
                <div class="pill-row">
                    <span class="pill coords-pill" id="popup-village-coords"></span>
                    <span class="pill owner-pill" id="popup-village-owner"></span>
                    <span class="pill points-pill" id="popup-village-points"></span>
                </div>
                <h4 id="popup-village-name"></h4>
                <div class="popup-actions">
                    <button id="popup-send-units" class="btn-primary">Send units</button>
                    <a id="popup-open-village" class="btn-ghost" href="#">Open profile</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const assetBase = '../img/tw_map';
const grassTiles = [`${assetBase}/gras1.png`, `${assetBase}/gras2.png`, `${assetBase}/gras3.png`, `${assetBase}/gras4.png`];
const forestTiles = [
    `${assetBase}/forest0000.png`, `${assetBase}/forest0001.png`, `${assetBase}/forest0010.png`, `${assetBase}/forest0011.png`,
    `${assetBase}/forest0100.png`, `${assetBase}/forest0101.png`, `${assetBase}/forest0110.png`, `${assetBase}/forest0111.png`,
    `${assetBase}/forest1000.png`, `${assetBase}/forest1001.png`, `${assetBase}/forest1010.png`, `${assetBase}/forest1011.png`,
    `${assetBase}/forest1100.png`, `${assetBase}/forest1101.png`, `${assetBase}/forest1110.png`, `${assetBase}/forest1111.png`
];
const bergTiles = [`${assetBase}/berg1.png`, `${assetBase}/berg2.png`, `${assetBase}/berg3.png`, `${assetBase}/berg4.png`];
const overlayIcons = {
    own: `${assetBase}/map_v6.png`,
    player: `${assetBase}/map_v4.png`,
    barbarian: `${assetBase}/map_v2.png`
};
const tileSize = { width: 53, height: 38 };
const pointBrackets = [0, 300, 1000, 3000, 9000, 12000];

const currentVillageId = <?php echo $village_id ?? 'null'; ?>;
const homeVillage = <?php echo $village ? json_encode([
    'id' => $village['id'],
    'x' => $village['x_coord'],
    'y' => $village['y_coord'],
    'name' => $village['name']
]) : 'null'; ?>;
const initialCenter = { x: <?php echo $center_x; ?>, y: <?php echo $center_y; ?> };
const initialSize = <?php echo $size; ?>;

const mapState = {
    center: { ...initialCenter },
    size: initialSize,
    byCoord: {}
};

document.addEventListener('DOMContentLoaded', () => {
    const sizeInput = document.getElementById('map-size');
    const sizeLabel = document.getElementById('map-size-label');
    sizeInput.addEventListener('input', () => {
        sizeLabel.textContent = `${sizeInput.value}x${sizeInput.value}`;
    });
    sizeInput.addEventListener('change', () => {
        const newSize = normalizeSize(parseInt(sizeInput.value, 10));
        loadMap(mapState.center.x, mapState.center.y, newSize);
    });

    document.querySelectorAll('.nav-btn').forEach(btn => {
        const dx = parseInt(btn.dataset.dx || 0, 10);
        const dy = parseInt(btn.dataset.dy || 0, 10);
        if (!isNaN(dx) || !isNaN(dy)) {
            btn.addEventListener('click', () => moveMap(dx, dy));
        }
        if (btn.dataset.home) {
            btn.addEventListener('click', jumpToHome);
        }
    });

    document.getElementById('jump-form').addEventListener('submit', handleJumpForm);

    document.getElementById('map-grid').addEventListener('click', (event) => {
        const tile = event.target.closest('.map-tile');
        if (tile && tile.dataset.x !== undefined && tile.dataset.y !== undefined) {
            const x = parseInt(tile.dataset.x, 10);
            const y = parseInt(tile.dataset.y, 10);
            showVillagePopup(x, y);
        }
    });

    document.querySelector('#map-popup .popup-close-btn').addEventListener('click', hideVillagePopup);

    document.getElementById('popup-send-units').addEventListener('click', function() {
        const targetVillageId = this.dataset.villageId;
        if (targetVillageId && currentVillageId) {
            fetch(`../combat/attack.php?target_village_id=${targetVillageId}&source_village_id=${currentVillageId}&ajax=1`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('generic-modal-content').innerHTML = html;
                    document.getElementById('generic-modal').style.display = 'block';
                    hideVillagePopup();
                })
                .catch(error => console.error('Error loading attack form:', error));
        }
    });

    document.addEventListener('click', (event) => {
        const popup = document.getElementById('map-popup');
        const isClickInsidePopup = popup.contains(event.target);
        const isClickOnTile = event.target.closest('.map-tile');
        if (!isClickInsidePopup && !isClickOnTile && popup.style.display !== 'none') {
            hideVillagePopup();
        }
    });

    document.getElementById('map-popup').addEventListener('click', (event) => {
        event.stopPropagation();
    });

    loadMap(mapState.center.x, mapState.center.y, mapState.size);
});

function normalizeSize(rawSize) {
    const clamped = Math.max(7, Math.min(31, rawSize || 15));
    return clamped % 2 === 0 ? clamped - 1 : clamped;
}

function hashCoords(x, y) {
    return Math.abs((x * 73856093) ^ (y * 19349663));
}

function getTerrainTile(x, y) {
    const hash = hashCoords(x, y);
    const roll = hash % 100;
    if (roll < 6) {
        return bergTiles[hash % bergTiles.length];
    }
    if (roll < 20) {
        return forestTiles[hash % forestTiles.length];
    }
    return grassTiles[hash % grassTiles.length];
}

function getVillageLevel(points) {
    let level = 1;
    for (let i = 0; i < pointBrackets.length; i++) {
        if (points >= pointBrackets[i]) {
            level = i + 1;
        }
    }
    return Math.min(level, 6);
}

function getVillageSprite(village) {
    const prefix = village.type === 'barbarian' ? 'b' : 'v';
    const level = getVillageLevel(village.points || 0);
    return `${assetBase}/${prefix}${level}.png`;
}

function getOverlayIcon(village) {
    if (currentVillageId && village.id === currentVillageId) {
        return overlayIcons.own;
    }
    if (village.type === 'barbarian') {
        return overlayIcons.barbarian;
    }
    return overlayIcons.player;
}

function indexVillages(villages) {
    const map = {};
    villages.forEach(v => {
        map[`${v.x}:${v.y}`] = v;
    });
    return map;
}

async function fetchMapData(targetX, targetY, targetSize) {
    const url = `map_data.php?x=${encodeURIComponent(targetX)}&y=${encodeURIComponent(targetY)}&size=${encodeURIComponent(targetSize)}`;
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error(`Map fetch failed with status ${response.status}`);
    }
    return response.json();
}

async function loadMap(targetX, targetY, targetSize) {
    try {
        const size = normalizeSize(targetSize);
        const data = await fetchMapData(targetX, targetY, size);
        mapState.center = data.center;
        mapState.size = data.size;
        mapState.byCoord = indexVillages(data.villages || []);
        renderMap();
        updateControls();
        updateUrl(mapState.center.x, mapState.center.y, mapState.size);
    } catch (error) {
        console.error('Failed to load map data:', error);
    }
}

function renderMap() {
    const mapGrid = document.getElementById('map-grid');
    mapGrid.innerHTML = '';
    const size = mapState.size;
    const radius = Math.floor((size - 1) / 2);
    const fragment = document.createDocumentFragment();

    mapGrid.style.gridTemplateColumns = `repeat(${size}, ${tileSize.width}px)`;
    mapGrid.style.gridTemplateRows = `repeat(${size}, ${tileSize.height}px)`;

    for (let y = 0; y < size; y++) {
        const coordY = mapState.center.y - radius + y;
        for (let x = 0; x < size; x++) {
            const coordX = mapState.center.x - radius + x;
            const key = `${coordX}:${coordY}`;
            const village = mapState.byCoord[key];
            const tile = document.createElement('div');
            tile.classList.add('map-tile');
            tile.dataset.x = coordX;
            tile.dataset.y = coordY;
            tile.style.backgroundImage = `url(${getTerrainTile(coordX, coordY)})`;

            if (village) {
                const villageLayer = document.createElement('div');
                villageLayer.classList.add('village-layer');
                villageLayer.style.backgroundImage = `url(${getVillageSprite(village)})`;
                tile.appendChild(villageLayer);

                const overlay = document.createElement('img');
                overlay.classList.add('overlay-icon');
                overlay.src = getOverlayIcon(village);
                overlay.alt = 'Village marker';
                tile.appendChild(overlay);

                const label = document.createElement('div');
                label.classList.add('village-label');
                label.textContent = village.name;
                tile.appendChild(label);
            } else {
                tile.classList.add('empty');
            }

            const coordsEl = document.createElement('div');
            coordsEl.classList.add('coords');
            coordsEl.textContent = `${coordX}|${coordY}`;
            tile.appendChild(coordsEl);

            fragment.appendChild(tile);
        }
    }

    mapGrid.appendChild(fragment);
}

function updateControls() {
    const centerDisplay = document.getElementById('toolbar-center');
    if (centerDisplay) {
        centerDisplay.textContent = `${mapState.center.x}|${mapState.center.y}`;
    }
    const sizeInput = document.getElementById('map-size');
    const sizeLabel = document.getElementById('map-size-label');
    if (sizeInput) sizeInput.value = mapState.size;
    if (sizeLabel) sizeLabel.textContent = `${mapState.size}x${mapState.size}`;
}

function updateUrl(x, y, size) {
    const params = new URLSearchParams(window.location.search);
    params.set('x', x);
    params.set('y', y);
    params.set('size', size);
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.replaceState({}, '', newUrl);
}

function moveMap(dx, dy) {
    const stride = Math.max(5, Math.floor(mapState.size / 2));
    loadMap(mapState.center.x + dx * stride, mapState.center.y + dy * stride, mapState.size);
}

function jumpToHome() {
    if (homeVillage) {
        loadMap(homeVillage.x, homeVillage.y, mapState.size);
    }
}

function handleJumpForm(event) {
    event.preventDefault();
    const xInput = document.getElementById('jump-x');
    const yInput = document.getElementById('jump-y');
    const targetX = parseInt(xInput.value, 10);
    const targetY = parseInt(yInput.value, 10);
    if (Number.isFinite(targetX) && Number.isFinite(targetY)) {
        loadMap(targetX, targetY, mapState.size);
    }
}

function showVillagePopup(x, y) {
    const key = `${x}:${y}`;
    const village = mapState.byCoord[key];
    const popup = document.getElementById('map-popup');
    if (!village || !popup) {
        hideVillagePopup();
        return;
    }

    const popupVillageName = document.getElementById('popup-village-name');
    const popupVillageOwner = document.getElementById('popup-village-owner');
    const popupVillageCoords = document.getElementById('popup-village-coords');
    const popupVillagePoints = document.getElementById('popup-village-points');
    const popupSendUnitsButton = document.getElementById('popup-send-units');
    const popupOpenVillage = document.getElementById('popup-open-village');

    popupVillageName.textContent = village.name;
    popupVillageOwner.textContent = village.owner || 'Barbarian village';
    popupVillageCoords.textContent = `${x}|${y}`;
    popupVillagePoints.textContent = `${village.points || 0} pts`;

    popupSendUnitsButton.dataset.villageId = village.id;
    if (village.user_id && village.user_id !== -1) {
        popupOpenVillage.style.display = 'inline-block';
        popupOpenVillage.href = `../player/player.php?id=${village.user_id}`;
    } else {
        popupOpenVillage.style.display = 'none';
    }

    if (village.id === currentVillageId) {
        popupSendUnitsButton.style.display = 'none';
    } else {
        popupSendUnitsButton.style.display = 'block';
    }

    const tileElement = document.querySelector(`.map-tile[data-x="${x}"][data-y="${y}"]`);
    if (tileElement) {
        const tileRect = tileElement.getBoundingClientRect();
        const container = document.getElementById('game-container');
        const containerRect = container.getBoundingClientRect();
        let popupLeft = tileRect.right + 12;
        let popupTop = tileRect.top + tileRect.height / 2;

        if (popupLeft + popup.offsetWidth > window.innerWidth - 20) {
            popupLeft = tileRect.left - popup.offsetWidth - 12;
        }

        popup.style.left = `${popupLeft - containerRect.left}px`;
        popup.style.top = `${popupTop - containerRect.top - popup.offsetHeight / 2}px`;
    }

    popup.style.display = 'block';
}

function hideVillagePopup() {
    const popup = document.getElementById('map-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}
</script>

<style>
:root {
    --panel-bg: rgba(255, 255, 255, 0.9);
    --panel-border: #d2b17a;
    --accent: #a26a31;
    --accent-strong: #8d4c1f;
    --tile-width: 53px;
    --tile-height: 38px;
}

.map-page {
    background: radial-gradient(circle at 20% 20%, #f3e3c2, #e4cfa0 60%, #d6bb83);
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
    gap: 1px;
}

.map-tile {
    position: relative;
    width: var(--tile-width);
    height: var(--tile-height);
    background-size: cover;
    background-position: center;
    overflow: hidden;
    cursor: pointer;
    border-radius: 4px;
}

.map-tile:hover {
    outline: 1px solid #d19b3a;
    z-index: 2;
}

.village-layer {
    position: absolute;
    inset: 0;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

.overlay-icon {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
}

.village-label {
    position: absolute;
    left: 4px;
    bottom: 18px;
    padding: 2px 6px;
    border-radius: 6px;
    font-size: 11px;
    background: rgba(0,0,0,0.55);
    color: #fff;
    max-width: calc(var(--tile-width) - 8px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 120ms ease;
}

.map-tile:hover .village-label {
    opacity: 1;
}

.coords {
    position: absolute;
    bottom: 2px;
    right: 4px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    font-size: 10px;
    padding: 2px 4px;
    border-radius: 6px;
}

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
    width: 32px;
    height: 24px;
    border-radius: 4px;
}

.map-popup {
    position: absolute;
    background: #fffdf8;
    border: 1px solid var(--panel-border);
    border-radius: 12px;
    padding: 10px 12px;
    box-shadow: 0 18px 26px rgba(0,0,0,0.16);
    z-index: 20;
    min-width: 240px;
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
.points-pill { background: #f6e7d3; border-color: #e0c59a; }

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
