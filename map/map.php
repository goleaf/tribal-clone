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
$userAllyId = null;
$allyStmt = $conn->prepare("SELECT ally_id FROM users WHERE id = ? LIMIT 1");
if ($allyStmt) {
    $allyStmt->bind_param("i", $user_id);
    $allyStmt->execute();
    $allyResult = $allyStmt->get_result()->fetch_assoc();
    $userAllyId = $allyResult['ally_id'] ?? null;
    $allyStmt->close();
}

$defaultX = $village['x_coord'] ?? 0;
$defaultY = $village['y_coord'] ?? 0;

$worldSize = defined('WORLD_SIZE') ? (int)WORLD_SIZE : 1000;
$center_x = isset($_GET['x']) ? (int)$_GET['x'] : $defaultX;
$center_y = isset($_GET['y']) ? (int)$_GET['y'] : $defaultY;
$size = isset($_GET['size']) ? max(7, min(31, (int)$_GET['size'])) : 15;
$center_x = max(0, min($worldSize - 1, $center_x));
$center_y = max(0, min($worldSize - 1, $center_y));

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
        <section class="map-subtoolbar">
            <div class="filter-card">
                <div class="filter-row">
                    <label><input type="checkbox" id="filter-own" checked> My villages</label>
                    <label><input type="checkbox" id="filter-tribe" checked> Tribe</label>
                    <label><input type="checkbox" id="filter-allies" checked> Allies / NAP / Truce</label>
                    <label><input type="checkbox" id="filter-enemies" checked> Enemies</label>
                    <label><input type="checkbox" id="filter-neutral" checked> Neutral</label>
                    <label><input type="checkbox" id="filter-barbs" checked> Barbarians</label>
                </div>
                <div class="filter-row secondary-filters">
                    <label><input type="checkbox" id="filter-players" checked> Non-tribe players (master)</label>
                    <label><input type="checkbox" id="filter-marked"> Marked only</label>
                </div>
                <div class="filter-row secondary-filters">
                    <label><input type="checkbox" id="filter-commands-incoming" checked> Incoming</label>
                    <label><input type="checkbox" id="filter-commands-outgoing" checked> Outgoing</label>
                    <label><input type="checkbox" id="filter-commands-support" checked> Support</label>
                </div>
                <div class="filter-row secondary-filters">
                    <label for="filter-activity" style="font-weight:600;">Activity</label>
                    <select id="filter-activity">
                        <option value="any">Any</option>
                        <option value="1h">Active ≤1h</option>
                        <option value="6h">Active ≤6h</option>
                        <option value="24h" selected>Active ≤24h</option>
                        <option value="72h">Active ≤72h</option>
                        <option value="stale">Inactive/Stale</option>
                    </select>
                </div>
                <div class="filter-hint">Filters are instant. Use the popup to mark reservations or add notes.</div>
                <div class="filter-row secondary-filters">
                    <label><input type="checkbox" id="high-contrast-toggle"> High-contrast map</label>
                    <label><input type="checkbox" id="reduced-motion-toggle"> Reduced motion</label>
                </div>
            </div>
            <div class="filter-card" style="max-width:320px;">
                <form id="bookmark-search-form" style="display:flex;gap:8px;align-items:center;">
                    <label for="bookmark-search-input" style="font-weight:600;white-space:nowrap;">Find bookmark</label>
                    <input type="text" id="bookmark-search-input" placeholder="Note text or village id" style="flex:1; padding:6px 8px;">
                    <button type="submit" class="btn-ghost" style="white-space:nowrap;">Go</button>
                </form>
                <div id="bookmark-search-status" class="filter-hint"></div>
            </div>
        </section>
        <div class="map-alert" id="movements-warning" style="display:none;">
            Movement view is limited to prevent slowdowns. Zoom closer or apply filters to see more details.
        </div>
        <div class="map-alert" id="map-rate-warning" style="display:none;">
            Map updates are temporarily slowed. Please wait a moment before panning again.
        </div>

            <div class="map-wrapper">
                <div class="map-grid-shell">
                    <div id="map-grid" class="map-grid" role="grid" aria-label="World map"></div>
                    <div id="map-skeleton" class="map-skeleton" aria-hidden="true">
                        <div class="skeleton-row"></div>
                        <div class="skeleton-row"></div>
                        <div class="skeleton-row"></div>
                        <div class="skeleton-row"></div>
                    </div>
                    <div id="map-loading" class="map-loading" aria-live="polite">
                        <span class="spinner"></span>
                        <span>Loading map…</span>
                    </div>
                </div>
                <aside class="mini-map-card">
                <div class="mini-map-header">
                    <span>Minimap</span>
                    <button id="mini-center-home" class="btn-ghost" title="Center on home">⌂</button>
                </div>
                <canvas id="mini-map" width="180" height="180" aria-label="Minimap"></canvas>
                <div class="mini-map-legend">
                    <span class="legend own"></span> You
                    <span class="legend ally"></span> Ally
                    <span class="legend enemy"></span> Enemy
                    <span class="legend neutral"></span> Neutral
                    <span class="legend barb"></span> Barb
                </div>
            </aside>
            <aside class="map-legend">
                <h4>Legend</h4>
                <div class="legend-row"><img src="../img/tw_map/map_v6.png" alt="Own"> <span>Your village</span></div>
                <div class="legend-row"><img src="../img/tw_map/map_v4.png" alt="Player"> <span>Player village</span></div>
                <div class="legend-row"><img src="../img/tw_map/map_v2.png" alt="Barbarian"> <span>Barbarian village</span></div>
                <div class="legend-row"><span class="legend-dot relation-own"></span> <span>Your tribe</span></div>
                <div class="legend-row"><span class="legend-dot relation-ally"></span> <span>Ally</span></div>
                <div class="legend-row"><span class="legend-dot relation-nap"></span> <span>NAP</span></div>
                <div class="legend-row"><span class="legend-dot relation-truce"></span> <span>Truce/Ceasefire</span></div>
                <div class="legend-row"><span class="legend-dot relation-enemy"></span> <span>Enemy</span></div>
                <div class="legend-row"><span class="legend-dot relation-neutral"></span> <span>Neutral</span></div>
                <div class="legend-row"><img src="../img/tw_map/reserved_player.png" alt="Reserved"> <span>Reserved (self)</span></div>
                <div class="legend-row"><img src="../img/tw_map/reserved_team.png" alt="Team reservation"> <span>Reserved (tribe)</span></div>
                <div class="legend-row"><img src="../img/tw_map/incoming_attack.png" alt="Incoming"> <span>Incoming attack</span></div>
                <div class="legend-row"><img src="../img/tw_map/attack.png" alt="Outgoing"> <span>Outgoing command</span></div>
                <div class="legend-row"><img src="../img/tw_map/return.png" alt="Return"> <span>Returning command</span></div>
                <div class="legend-row"><img src="../img/tw_map/village_notes.png" alt="Note"> <span>Village note</span></div>
                <div class="legend-row"><img src="../img/tw_map/map_free.png" alt="Empty"> <span>Unsettled lands</span></div>
            </aside>
        </div>

        <div id="map-popup" class="map-popup" style="display: none;">
            <button class="popup-close-btn" aria-label="Close">&times;</button>
            <div class="popup-body">
                <div class="pill-row">
                    <span class="pill coords-pill" id="popup-village-coords"></span>
                    <span class="pill distance-pill" id="popup-village-distance" style="display:none;"></span>
                    <span class="pill owner-pill" id="popup-village-owner"></span>
                    <span class="pill points-pill" id="popup-village-points"></span>
                </div>
                <h4 id="popup-village-name"></h4>
                <div class="popup-actions">
                    <button id="popup-attack" class="btn-primary">Quick attack</button>
                    <button id="popup-support" class="btn-secondary">Quick support</button>
                    <button id="popup-send-units" class="btn-ghost">Open command</button>
                    <a id="popup-open-village" class="btn-ghost" href="#">Open profile</a>
                </div>
                <div class="travel-card">
                    <div class="travel-row">
                        <label for="travel-unit-select">Travel from home:</label>
                        <select id="travel-unit-select"></select>
                    </div>
                    <div class="travel-meta">
                        <span id="travel-time-display">–</span>
                        <span id="travel-distance-display"></span>
                    </div>
                    <div class="travel-error" id="travel-error" style="display:none;"></div>
                </div>
                <div class="movement-summary" id="movement-summary" style="display:none;"></div>
                <div class="note-card">
                    <div class="reserve-row">
                        <label class="reserve-toggle">
                            <input type="checkbox" id="reserve-village">
                            Reserve this village
                        </label>
                        <select id="reserve-scope" aria-label="Reservation scope">
                            <option value="self">For me</option>
                            <option value="tribe">For tribe</option>
                        </select>
                    </div>
                    <label for="village-note">Note</label>
                    <textarea id="village-note" rows="3" placeholder="Add a short note"></textarea>
                    <div class="note-actions">
                        <button id="save-note" class="btn-primary">Save note</button>
                        <span class="note-status" id="note-status"></span>
                    </div>
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
    tiers: [
        null,
        `${assetBase}/map_v1.png`,
        `${assetBase}/map_v2.png`,
        `${assetBase}/map_v3.png`,
        `${assetBase}/map_v4.png`,
        `${assetBase}/map_v5.png`,
        `${assetBase}/map_v6.png`
    ],
    own: `${assetBase}/map_v6.png`,
    reservedPlayer: `${assetBase}/reserved_player.png`,
    reservedTeam: `${assetBase}/reserved_team.png`,
    incoming: `${assetBase}/incoming_attack.png`,
    attack: `${assetBase}/attack.png`,
    return: `${assetBase}/return.png`,
    axeAttack: `${assetBase}/axe_attack.png`,
    axeReturn: `${assetBase}/axe_return.png`,
    note: `${assetBase}/village_notes.png`,
    free: `${assetBase}/map_free.png`
};
const movementIcons = {
    incoming: overlayIcons.incoming,
    attack: overlayIcons.attack,
    return: overlayIcons.return,
    axe_attack: overlayIcons.axeAttack,
    axe_return: overlayIcons.axeReturn
};
const tileSize = { width: 53, height: 38 };
const pointBrackets = [0, 300, 1000, 3000, 9000, 12000];
const MOVEMENT_RENDER_LIMIT_NEAR = 3;
const MOVEMENT_RENDER_LIMIT_FAR = 1;
const MOVEMENT_HIDE_THRESHOLD = 25; // hide movement icons entirely on very far zooms
const PERF_SAMPLE_RATE = 0.05; // 5% of map loads

const currentUserId = <?php echo (int)$user_id; ?>;
const currentUserAllyId = <?php echo $userAllyId !== null ? (int)$userAllyId : 'null'; ?>;
const currentTribeId = <?php echo $userAllyId !== null ? (int)$userAllyId : 'null'; ?>;
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
    byCoord: {},
    players: {},
    tribes: {},
    tribeDiplomacy: {},
    myTribeId: currentUserAllyId || null,
    unitSpeeds: {}
};
let annotations = loadAnnotations();
trimAnnotations(50);
let mapFetchInFlight = false;
let mapPollInterval = null;
let worldBounds = null;
const mapPollMs = 15000;
const MAP_FETCH_DEBOUNCE_MS = 180;
const MAP_SKELETON_DELAY_MS = 120;
let mapLoadTimer = null;
let lastQueuedRequest = null;
let mapAbortController = null;
const HIGH_CONTRAST_KEY = 'map_high_contrast';
const REDUCED_MOTION_KEY = 'map_reduced_motion';
let mapHighContrast = false;
let mapReducedMotion = false;
let lastMapPayloadBytes = null;
const PERF_SAMPLE_RATE = 0.1;
const filters = {
    barbarians: true,
    players: true,
    tribe: true,
    own: true,
    markedOnly: false,
    allies: true,
    enemies: true,
    neutral: true,
    activity: 'any',
    commands: {
        incoming: true,
        outgoing: true,
        support: true
    }
};
const mapLoadingEl = document.getElementById('map-loading');
const mapSkeletonEl = document.getElementById('map-skeleton');
const highContrastToggle = document.getElementById('high-contrast-toggle');
const reducedMotionToggle = document.getElementById('reduced-motion-toggle');
let mapSkeletonTimer = null;
const movementsWarningEl = document.getElementById('movements-warning');
const mapRateWarningEl = document.getElementById('map-rate-warning');
const interactiveSelectors = ['input', 'textarea', 'select', 'button', '[contenteditable="true"]'];

function setMapLoading(isLoading) {
    if (mapLoadingEl) {
        mapLoadingEl.style.display = isLoading ? 'flex' : 'none';
    }
    if (mapSkeletonEl) {
        if (isLoading) {
            clearTimeout(mapSkeletonTimer);
            mapSkeletonTimer = setTimeout(() => {
                mapSkeletonEl.style.display = 'block';
            }, MAP_SKELETON_DELAY_MS);
        } else {
            clearTimeout(mapSkeletonTimer);
            mapSkeletonEl.style.display = 'none';
        }
    }
    const grid = document.getElementById('map-grid');
    if (grid) {
        grid.classList.toggle('is-loading', isLoading);
    }
}

function loadAnnotations() {
    try {
        const raw = localStorage.getItem('tw_map_annotations');
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        console.warn('Failed to read map annotations from storage', e);
        return {};
    }
}

function saveAnnotations() {
    try {
        localStorage.setItem('tw_map_annotations', JSON.stringify(annotations));
    } catch (e) {
        console.warn('Failed to persist map annotations', e);
    }
}

function trimAnnotations(limit = 50) {
    const entries = Object.entries(annotations || {});
    if (entries.length <= limit) return;
    entries.sort((a, b) => ((a[1]?.saved_at || 0) - (b[1]?.saved_at || 0)));
    const trimmed = entries.slice(entries.length - limit);
    annotations = {};
    trimmed.forEach(([id, data]) => {
        annotations[id] = data;
    });
    saveAnnotations();
}

function setAnnotation(villageId, data, coords = null) {
    const existing = annotations[villageId] || { note: '', reserved: '' };
    const updated = { ...existing, ...data, saved_at: Date.now() };
    if (coords && Number.isFinite(coords.x) && Number.isFinite(coords.y)) {
        updated.x = coords.x;
        updated.y = coords.y;
    }

    // Normalize reserved flag to '', 'self', or 'tribe'
    if (updated.reserved !== 'self' && updated.reserved !== 'tribe') {
        updated.reserved = '';
    }

    annotations[villageId] = updated;
    if (!annotations[villageId].note && !annotations[villageId].reserved) {
        delete annotations[villageId];
    }
    saveAnnotations();
    trimAnnotations(50);
}

function getAnnotation(villageId) {
    return annotations[villageId] || { note: '', reserved: '' };
}

function updateNoteStatus(message) {
    const status = document.getElementById('note-status');
    if (!status) return;
    status.textContent = message || '';
    if (message) {
        setTimeout(() => {
            status.textContent = '';
        }, 1500);
    }
}

function findAnnotationMatch(query) {
    if (!query) return null;
    const q = query.toLowerCase();
    const entries = Object.entries(annotations || {});
    for (const [vId, data] of entries) {
        const idMatch = vId === q || vId === query || vId === String(parseInt(query, 10));
        const noteMatch = (data.note || '').toLowerCase().includes(q);
        const reservedMatch = (data.reserved || '').toLowerCase().includes(q);
        if (idMatch || noteMatch || reservedMatch) {
            return { villageId: parseInt(vId, 10), data };
        }
    }
    return null;
}

async function jumpToBookmark(match) {
    if (!match) return false;
    const coords = { x: match.data.x, y: match.data.y };
    const hasCoords = Number.isFinite(coords.x) && Number.isFinite(coords.y);
    if (hasCoords) {
        await loadMap(coords.x, coords.y, mapState.size, { skipUrl: false });
        showVillagePopup(coords.x, coords.y);
        return true;
    }
    // Fallback: try to locate by village id in current map data
    const village = Object.values(mapState.byCoord || {}).find(v => v.id === match.villageId);
    if (village) {
        await loadMap(village.x, village.y, mapState.size, { skipUrl: false });
        showVillagePopup(village.x, village.y);
        return true;
    }
    return false;
}

document.addEventListener('DOMContentLoaded', () => {
    initHighContrast();
    initReducedMotion();
    document.addEventListener('keydown', handleMapKeydown);
    const sizeInput = document.getElementById('map-size');
    const sizeLabel = document.getElementById('map-size-label');
    sizeInput.addEventListener('input', () => {
        sizeLabel.textContent = `${sizeInput.value}x${sizeInput.value}`;
    });
    sizeInput.addEventListener('change', () => {
        const newSize = normalizeSize(parseInt(sizeInput.value, 10));
        sizeLabel.textContent = `${newSize}x${newSize}`;
        requestMapLoad(mapState.center.x, mapState.center.y, newSize);
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
    const miniHomeBtn = document.getElementById('mini-center-home');
    if (miniHomeBtn) {
        miniHomeBtn.addEventListener('click', jumpToHome);
    }

    document.querySelector('#map-popup .popup-close-btn').addEventListener('click', hideVillagePopup);

    document.getElementById('popup-send-units').addEventListener('click', () => {
        const targetVillageId = document.getElementById('popup-send-units').dataset.villageId;
        openAttackModal(targetVillageId, null);
    });
    document.getElementById('popup-attack').addEventListener('click', () => {
        const targetVillageId = document.getElementById('popup-attack').dataset.villageId;
        openAttackModal(targetVillageId, 'attack');
    });
    document.getElementById('popup-support').addEventListener('click', () => {
        const targetVillageId = document.getElementById('popup-support').dataset.villageId;
        openAttackModal(targetVillageId, 'support');
    });

    const reserveToggle = document.getElementById('reserve-village');
    const reserveScope = document.getElementById('reserve-scope');
    const noteTextarea = document.getElementById('village-note');
    const saveNoteBtn = document.getElementById('save-note');
    reserveToggle.addEventListener('change', () => {
        const vid = reserveToggle.dataset.villageId ? parseInt(reserveToggle.dataset.villageId, 10) : null;
        if (!vid) return;
        const coords = {
            x: reserveToggle.dataset.x ? parseInt(reserveToggle.dataset.x, 10) : null,
            y: reserveToggle.dataset.y ? parseInt(reserveToggle.dataset.y, 10) : null
        };
        setAnnotation(vid, { reserved: reserveToggle.checked ? (reserveScope.value || 'self') : '' }, coords);
        renderMap();
        updateNoteStatus('Reservation updated');
    });
    reserveScope.addEventListener('change', () => {
        const vid = reserveScope.dataset.villageId ? parseInt(reserveScope.dataset.villageId, 10) : null;
        if (!vid) return;
        if (reserveToggle.checked) {
            const coords = {
                x: reserveScope.dataset.x ? parseInt(reserveScope.dataset.x, 10) : null,
                y: reserveScope.dataset.y ? parseInt(reserveScope.dataset.y, 10) : null
            };
            setAnnotation(vid, { reserved: reserveScope.value || 'self' }, coords);
            renderMap();
            updateNoteStatus('Reservation scope updated');
        }
    });
    saveNoteBtn.addEventListener('click', () => {
        const vid = noteTextarea.dataset.villageId ? parseInt(noteTextarea.dataset.villageId, 10) : null;
        if (!vid) return;
        const coords = {
            x: noteTextarea.dataset.x ? parseInt(noteTextarea.dataset.x, 10) : null,
            y: noteTextarea.dataset.y ? parseInt(noteTextarea.dataset.y, 10) : null
        };
        setAnnotation(vid, { note: noteTextarea.value.trim() }, coords);
        renderMap();
        updateNoteStatus('Note saved');
    });

    document.querySelectorAll('.filter-card input[type="checkbox"]').forEach(box => {
        box.addEventListener('change', () => {
            filters.barbarians = document.getElementById('filter-barbs').checked;
            filters.players = document.getElementById('filter-players').checked;
            filters.tribe = document.getElementById('filter-tribe').checked;
            filters.own = document.getElementById('filter-own').checked;
            filters.markedOnly = document.getElementById('filter-marked').checked;
            const allyBox = document.getElementById('filter-allies');
            const enemyBox = document.getElementById('filter-enemies');
            const neutralBox = document.getElementById('filter-neutral');
            const activitySelect = document.getElementById('filter-activity');
            if (allyBox) filters.allies = allyBox.checked;
            if (enemyBox) filters.enemies = enemyBox.checked;
            if (neutralBox) filters.neutral = neutralBox.checked;
            if (activitySelect) filters.activity = activitySelect.value || 'any';
            const cmdIn = document.getElementById('filter-commands-incoming');
            const cmdOut = document.getElementById('filter-commands-outgoing');
            const cmdSup = document.getElementById('filter-commands-support');
            filters.commands.incoming = cmdIn ? cmdIn.checked : true;
            filters.commands.outgoing = cmdOut ? cmdOut.checked : true;
            filters.commands.support = cmdSup ? cmdSup.checked : true;
            renderMap();
        });
    });

    const bookmarkForm = document.getElementById('bookmark-search-form');
    const bookmarkInput = document.getElementById('bookmark-search-input');
    const bookmarkStatus = document.getElementById('bookmark-search-status');
    if (bookmarkForm && bookmarkInput) {
        bookmarkForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const query = bookmarkInput.value.trim();
            if (!query) {
                if (bookmarkStatus) bookmarkStatus.textContent = 'Enter note text or village id.';
                return;
            }
            const match = findAnnotationMatch(query);
            if (!match) {
                if (bookmarkStatus) bookmarkStatus.textContent = 'No matching bookmark found.';
                return;
            }
            if (bookmarkStatus) bookmarkStatus.textContent = 'Opening bookmark...';
            const ok = await jumpToBookmark(match);
            if (!ok && bookmarkStatus) {
                bookmarkStatus.textContent = 'Bookmark not on map yet. Try loading the area.';
            } else if (bookmarkStatus) {
                bookmarkStatus.textContent = '';
            }
        });
    }

    if (travelUnitSelect) {
        travelUnitSelect.addEventListener('change', triggerTravelEstimate);
    }

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

    loadMap(mapState.center.x, mapState.center.y, mapState.size).then(() => {
        startMapPolling();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !mapFetchInFlight) {
            requestMapLoad(mapState.center.x, mapState.center.y, mapState.size, { skipUrl: true });
        }
    });
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

function getContinentNumber(x, y) {
    return Math.floor(x / 100) * 10 + Math.floor(y / 100);
}

function formatContinent(x, y) {
    const k = getContinentNumber(x, y);
    return `K${String(k).padStart(2, '0')}`;
}

function calculateDistanceTiles(fromX, fromY, toX, toY) {
    const dx = toX - fromX;
    const dy = toY - fromY;
    return Math.sqrt(dx * dx + dy * dy);
}

function formatDistance(distance, withUnit = true) {
    if (!Number.isFinite(distance)) return '-';
    const rounded = distance >= 10 ? Math.round(distance) : Math.round(distance * 10) / 10;
    const label = Number.isInteger(rounded) ? `${rounded}` : `${rounded.toFixed(1)}`;
    return withUnit ? `${label} tiles` : label;
}

function formatDirection(dx, dy) {
    const vertical = dy < -0.0001 ? 'N' : dy > 0.0001 ? 'S' : '';
    const horizontal = dx < -0.0001 ? 'W' : dx > 0.0001 ? 'E' : '';
    return `${vertical}${horizontal}`;
}

function formatDistanceFromHome(targetX, targetY) {
    if (!homeVillage || !Number.isFinite(homeVillage.x) || !Number.isFinite(homeVillage.y)) return '';
    const tiles = calculateDistanceTiles(homeVillage.x, homeVillage.y, targetX, targetY);
    if (tiles < 0.01) {
        return 'At your home village';
    }
    const numberLabel = formatDistance(tiles, false);
    const direction = formatDirection(targetX - homeVillage.x, targetY - homeVillage.y);
    const directionLabel = direction ? ` ${direction}` : '';
    const originLabel = homeVillage.name ? ` from ${homeVillage.name}` : ' from home';
    return `${numberLabel} tiles${directionLabel}${originLabel}`;
}

function getVillageRelation(village) {
    const isBarb = village.user_id === null || village.user_id === -1;
    if (isBarb) return 'barbarian';
    if (village.user_id === currentUserId) return 'own';

    const player = village.user_id ? mapState.players[village.user_id] : null;
    const tribeId = mapState.myTribeId || currentUserAllyId;
    const sameTribe = tribeId && player && player.ally_id === tribeId;
    if (sameTribe) return 'tribe';

    const dipStatus = player && player.ally_id && mapState.tribeDiplomacy ? mapState.tribeDiplomacy[player.ally_id] : null;
    const normalizedDip = typeof dipStatus === 'string' ? dipStatus.toLowerCase() : dipStatus;
    if (normalizedDip === 'ally' || normalizedDip === 'alliance') return 'ally';
    if (normalizedDip === 'nap') return 'nap';
    if (normalizedDip === 'truce' || normalizedDip === 'ceasefire') return 'truce';
    if (normalizedDip === 'enemy' || normalizedDip === 'war') return 'enemy';

    return 'neutral';
}

function getOverlayIcon(village, annotation = {}) {
    if (annotation.reserved === 'tribe') {
        return overlayIcons.reservedTeam;
    }
    if (annotation.reserved === 'self') {
        return overlayIcons.reservedPlayer;
    }
    if (village.reserved_by) {
        return overlayIcons.reservedPlayer;
    }
    if (village.reserved_team) {
        return overlayIcons.reservedTeam;
    }
    if (village.is_own || (currentVillageId && village.id === currentVillageId)) {
        return overlayIcons.own;
    }
    const level = getVillageLevel(village.points || 0);
    return overlayIcons.tiers[level] || overlayIcons.tiers[overlayIcons.tiers.length - 1];
}

function shouldRenderVillage(village, annotation = {}) {
    const relation = getVillageRelation(village);
    const activity = (village.activity_bucket || 'unknown').toString().toLowerCase();
    const hasMovements = Array.isArray(village.movements) && village.movements.length > 0;

    if (filters.markedOnly && !annotation.note && !annotation.reserved) {
        return false;
    }

    if (relation === 'barbarian') return filters.barbarians;
    if (relation === 'own') return filters.own;
    if (relation === 'tribe') return filters.tribe;
    if (!filters.players) return false;
    if (relation === 'ally' || relation === 'nap' || relation === 'truce') return filters.allies;
    if (relation === 'enemy') return filters.enemies;
    if (relation === 'neutral') return filters.neutral;

    switch (filters.activity) {
        case '1h':
            if (!['1h'].includes(activity)) return false;
            break;
        case '6h':
            if (!['1h', '6h'].includes(activity)) return false;
            break;
        case '24h':
            if (!['1h', '6h', '24h'].includes(activity)) return false;
            break;
        case '72h':
            if (!['1h', '6h', '24h', '72h'].includes(activity)) return false;
            break;
        case 'stale':
            if (!['stale', 'unknown'].includes(activity)) return false;
            break;
        default:
            break;
    }

    if (hasMovements) {
        const anyVisible = village.movements.some((move) => movementVisible(move));
        if (!anyVisible && filters.commands) {
            // If all movements filtered out and filters set, allow other filters to decide; do not hide village
        }
    }

    return true;
}

function getVillageRelationClass(village) {
    const relation = getVillageRelation(village);
    if (relation === 'barbarian') return 'relation-barb';
    if (relation === 'own') return 'relation-own';
    if (relation === 'tribe' || relation === 'ally') return 'relation-ally';
    if (relation === 'nap') return 'relation-nap';
    if (relation === 'truce') return 'relation-truce';
    if (relation === 'enemy') return 'relation-enemy';
    return 'relation-neutral';
}

function indexVillages(villages, playersMap) {
    const map = {};
    (villages || []).forEach(v => {
        const player = v.user_id ? playersMap[v.user_id] : null;
        const enriched = {
            ...v,
            owner: v.owner || (player ? player.username : null),
            ally_id: v.ally_id || (player ? player.ally_id : null)
        };
        map[`${enriched.x}:${enriched.y}`] = enriched;
    });
    return map;
}

function movementVisible(move) {
    if (!move || !filters.commands) return true;
    const type = (move.type || '').toString().toLowerCase();
    const isIncoming = type.includes('incoming');
    const isSupport = type.includes('support');
    const isOutgoing = !isIncoming && !isSupport; // treat attack/support originating from own as outgoing

    if (isIncoming && !filters.commands.incoming) return false;
    if (isSupport && !filters.commands.support) return false;
    if (isOutgoing && !filters.commands.outgoing) return false;
    return true;
}

async function fetchMapData(targetX, targetY, targetSize, controller) {
    const url = `map_data.php?x=${encodeURIComponent(targetX)}&y=${encodeURIComponent(targetY)}&size=${encodeURIComponent(targetSize)}`;
    const headers = {};
    if (window.__mapETag) {
        headers['If-None-Match'] = window.__mapETag;
    }
    if (window.__mapLastModified) {
        headers['If-Modified-Since'] = window.__mapLastModified;
    }
    const response = await fetch(url, { credentials: 'same-origin', headers, signal: controller?.signal });
    if (response.status === 429) {
        const retryAfter = parseInt(response.headers.get('Retry-After') || '1', 10);
        const err = new Error('Rate limited');
        err.code = 'RATE_LIMIT';
        err.retryAfterMs = (isNaN(retryAfter) ? 1 : retryAfter) * 1000;
        throw err;
    }
    if (response.status === 304) {
        if (window.__mapCache) {
            // Reuse cached payload; adjust center/size to requested params
            return {
                ...window.__mapCache,
                center: { x: targetX, y: targetY },
                size: targetSize
            };
        }
        // No cache available; fallback to fresh fetch without conditionals
        const refetch = await fetch(url, { credentials: 'same-origin', signal: controller?.signal });
        if (!refetch.ok) {
            throw new Error(`Map fetch failed with status ${refetch.status}`);
        }
        const freshData = await refetch.json();
        window.__mapETag = refetch.headers.get('ETag') || null;
        window.__mapLastModified = refetch.headers.get('Last-Modified') || null;
        window.__mapCache = freshData;
        return freshData;
    }
    if (response.status === 429) {
        const retryAfter = parseInt(response.headers.get('Retry-After') || '1', 10);
        throw new Error(`RATE_LIMIT:${isNaN(retryAfter) ? 1 : retryAfter}`);
    }
    if (!response.ok) {
        throw new Error(`Map fetch failed with status ${response.status}`);
    }
    const data = await response.json();
    window.__mapETag = response.headers.get('ETag') || null;
    window.__mapLastModified = response.headers.get('Last-Modified') || null;
    window.__mapCache = data;
    lastMapPayloadBytes = parseInt(response.headers.get('Content-Length') || '0', 10) || null;
    if (!lastMapPayloadBytes) {
        try {
            lastMapPayloadBytes = JSON.stringify(data).length;
        } catch (e) {
            lastMapPayloadBytes = null;
        }
    }
    return data;
}

function indexPlayers(players) {
    const map = {};
    (players || []).forEach(p => {
        if (!p || typeof p.id === 'undefined') return;
        map[p.id] = p;
    });
    return map;
}

function indexTribes(tribes) {
    const map = {};
    (tribes || []).forEach(t => {
        if (!t || typeof t.id === 'undefined') return;
        map[t.id] = t;
    });
    return map;
}

function openAttackModal(targetVillageId, preferredType) {
    if (!targetVillageId || !currentVillageId) return;
    const typeParam = preferredType ? `&preferred_attack_type=${preferredType}` : '';
    fetch(`../combat/attack.php?target_village_id=${targetVillageId}&source_village_id=${currentVillageId}&ajax=1${typeParam}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('generic-modal-content').innerHTML = html;
            document.getElementById('generic-modal').style.display = 'block';
            hideVillagePopup();
        })
        .catch(error => console.error('Error loading attack form:', error));
}

function requestMapLoad(targetX, targetY, targetSize, options = {}) {
    lastQueuedRequest = { x: targetX, y: targetY, size: targetSize, options };
    if (mapLoadTimer) {
        clearTimeout(mapLoadTimer);
    }
    mapLoadTimer = setTimeout(() => {
        // If a fetch is running, requeue with the latest request.
        if (mapFetchInFlight) {
            requestMapLoad(lastQueuedRequest.x, lastQueuedRequest.y, lastQueuedRequest.size, lastQueuedRequest.options);
            return;
        }
        const req = lastQueuedRequest;
        lastQueuedRequest = null;
        mapLoadTimer = null;
        loadMap(req.x, req.y, req.size, req.options);
    }, MAP_FETCH_DEBOUNCE_MS);
}

async function loadMap(targetX, targetY, targetSize, options = {}) {
    const { skipUrl = false } = options;
    if (mapAbortController) {
        mapAbortController.abort();
    }
    mapAbortController = new AbortController();
    mapFetchInFlight = true;
    setMapLoading(true);
    try {
        const size = normalizeSize(targetSize);
        const fetchStart = performance.now();
        const data = await fetchMapData(targetX, targetY, size, mapAbortController);
        const fetchMs = performance.now() - fetchStart;
        if (!data) {
            return;
        }
        if (movementsWarningEl) {
            movementsWarningEl.style.display = data.movements_truncated ? 'block' : 'none';
        }
        mapState.center = data.center || { x: targetX, y: targetY };
        mapState.size = data.size || size;
        mapState.players = indexPlayers(data.players || []);
        mapState.tribes = indexTribes(data.tribes || data.allies || []);
        mapState.byCoord = indexVillages(data.villages || [], mapState.players);
        mapState.tribeDiplomacy = data.diplomacy || data.tribeDiplomacy || {};
        mapState.myTribeId = data.my_tribe_id || currentUserAllyId || null;
        mapState.unitSpeeds = data.unit_speeds || {};
        worldBounds = data.world_bounds || worldBounds;
        const renderStart = performance.now();
        renderMap();
        renderMiniMap();
        updateControls();
        if (!skipUrl) {
            updateUrl(mapState.center.x, mapState.center.y, mapState.size);
        }
        const renderMs = performance.now() - renderStart;
        sampleMapPerf({
            fetch_ms: Math.round(fetchMs),
            render_ms: Math.round(renderMs),
            size: mapState.size,
            truncated: !!data.movements_truncated,
            center_x: mapState.center.x,
            center_y: mapState.center.y,
            ts: Date.now()
        });
    } catch (error) {
        if (error.name === 'AbortError') {
            // ignore
        } else if (error.code === 'RATE_LIMIT') {
            if (mapRateWarningEl) {
                mapRateWarningEl.style.display = 'block';
            }
            const retryAfter = Math.max(500, (error.retryAfterMs || 1000));
            console.warn(`Map fetch rate-limited. Retrying in ${Math.round(retryAfter / 1000)}s.`);
            setTimeout(() => {
                requestMapLoad(targetX, targetY, targetSize, { ...options, skipUrl: true });
            }, retryAfter);
        } else {
            console.error('Failed to load map data:', error);
        }
    } finally {
        mapFetchInFlight = false;
        setMapLoading(false);
        if (mapRateWarningEl && error?.code !== 'RATE_LIMIT') {
            mapRateWarningEl.style.display = 'none';
        }
        if (!mapLoadTimer && lastQueuedRequest) {
            const req = lastQueuedRequest;
            lastQueuedRequest = null;
            requestMapLoad(req.x, req.y, req.size, req.options);
        }
    }
}

function startMapPolling() {
    if (mapPollInterval) return;
    mapPollInterval = setInterval(() => {
        if (document.hidden) return;
        if (mapFetchInFlight) return;
        requestMapLoad(mapState.center.x, mapState.center.y, mapState.size, { skipUrl: true });
    }, mapPollMs);
}

function applyHighContrast(enabled) {
    mapHighContrast = !!enabled;
    if (highContrastToggle) {
        highContrastToggle.checked = mapHighContrast;
    }
    if (mapHighContrast) {
        document.body.classList.add('map-high-contrast');
        localStorage.setItem(HIGH_CONTRAST_KEY, '1');
    } else {
        document.body.classList.remove('map-high-contrast');
        localStorage.setItem(HIGH_CONTRAST_KEY, '0');
    }
}

function initHighContrast() {
    const stored = localStorage.getItem(HIGH_CONTRAST_KEY);
    const enabled = stored === '1';
    applyHighContrast(enabled);
    if (highContrastToggle) {
        highContrastToggle.addEventListener('change', (e) => {
            applyHighContrast(e.target.checked);
        });
    }
}

function applyReducedMotion(enabled) {
    mapReducedMotion = !!enabled;
    if (reducedMotionToggle) {
        reducedMotionToggle.checked = mapReducedMotion;
    }
    if (mapReducedMotion) {
        document.body.classList.add('map-reduced-motion');
        localStorage.setItem(REDUCED_MOTION_KEY, '1');
    } else {
        document.body.classList.remove('map-reduced-motion');
        localStorage.setItem(REDUCED_MOTION_KEY, '0');
    }
}

function initReducedMotion() {
    const stored = localStorage.getItem(REDUCED_MOTION_KEY);
    const enabled = stored === '1';
    applyReducedMotion(enabled);
    if (reducedMotionToggle) {
        reducedMotionToggle.addEventListener('change', (e) => {
            applyReducedMotion(e.target.checked);
        });
    }
}

function isTypingContext(target) {
    if (!target) return false;
    const tag = target.tagName ? target.tagName.toLowerCase() : '';
    if (interactiveSelectors.some(sel => target.matches?.(sel))) {
        return true;
    }
    // Also ignore keypresses when focus is on map popup inputs if added later
    return tag === 'input' || tag === 'textarea' || tag === 'select';
}

function handleMapKeydown(event) {
    const key = event.key;
    if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key)) {
        return;
    }
    if (isTypingContext(event.target)) {
        return;
    }
    let dx = 0;
    let dy = 0;
    if (key === 'ArrowUp') dy = -1;
    if (key === 'ArrowDown') dy = 1;
    if (key === 'ArrowLeft') dx = -1;
    if (key === 'ArrowRight') dx = 1;
    moveMap(dx, dy);
    event.preventDefault();
}

function sampleMapPerf(payload) {
    if (Math.random() > PERF_SAMPLE_RATE) return;
    const body = JSON.stringify(payload);
    const url = '../ajax/map/client_perf.php';
    if (navigator.sendBeacon) {
        navigator.sendBeacon(url, body);
    } else {
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body,
            keepalive: true,
            credentials: 'same-origin'
        }).catch(() => {});
    }
}

function renderMap() {
    const renderStart = performance.now();
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

            const annotation = village ? getAnnotation(village.id) : {};
            const showVillage = village && shouldRenderVillage(village, annotation);

            if (village && showVillage) {
                const relationClass = getVillageRelationClass(village);
                if (relationClass) {
                    tile.classList.add(relationClass);
                }
                if (village.is_protected) {
                    tile.classList.add('protected');
                }
                if (village.activity_bucket) {
                    tile.classList.add(`activity-${village.activity_bucket}`);
                }
                const villageLayer = document.createElement('div');
                villageLayer.classList.add('village-layer');
                villageLayer.style.backgroundImage = `url(${getVillageSprite(village)})`;
                tile.appendChild(villageLayer);

                const overlay = document.createElement('img');
                overlay.classList.add('overlay-icon');
                overlay.src = getOverlayIcon(village, annotation);
                overlay.alt = 'Village marker';
                tile.appendChild(overlay);

                const showMovements = mapState.size < MOVEMENT_HIDE_THRESHOLD;
                if (showMovements && Array.isArray(village.movements) && village.movements.length > 0) {
                    const visibleMoves = village.movements.filter(movementVisible);
                    if (visibleMoves.length > 0) {
                        const movementStack = document.createElement('div');
                        movementStack.classList.add('movement-stack');
                        const renderLimit = mapState.size > 19 ? MOVEMENT_RENDER_LIMIT_FAR : MOVEMENT_RENDER_LIMIT_NEAR;
                        visibleMoves.slice(0, renderLimit).forEach(move => {
                            const icon = document.createElement('img');
                            icon.classList.add('movement-icon');
                            icon.src = movementIcons[move.type] || overlayIcons.incoming;
                            icon.alt = move.type;
                            if (move.has_noble) {
                                icon.classList.add('noble-move');
                            }
                            const arrivalLabel = formatArrivalTime(move.arrival);
                            if (arrivalLabel) {
                                const directionLabel = move.type.includes('incoming') ? 'Incoming' : (move.type.includes('support') ? 'Support' : 'Outgoing');
                                const endpoint = move.source ? `${move.source.x}|${move.source.y}` : (move.target ? `${move.target.x}|${move.target.y}` : '');
                                const nobleTag = move.has_noble ? ' · Noble' : '';
                                icon.title = `${directionLabel}${endpoint ? ' ' + endpoint : ''} · ETA ${arrivalLabel}${nobleTag}`;
                            }
                            movementStack.appendChild(icon);
                        });
                        tile.appendChild(movementStack);
                    }
                }

                if (annotation.note) {
                    const noteIcon = document.createElement('img');
                    noteIcon.classList.add('note-icon');
                    noteIcon.src = overlayIcons.note;
                    noteIcon.alt = 'Note';
                    tile.appendChild(noteIcon);
                }
                if (annotation.reserved) {
                    tile.classList.add('reserved');
                }

                const label = document.createElement('div');
                label.classList.add('village-label');
                label.textContent = village.name;
                tile.appendChild(label);

                const meta = document.createElement('div');
                meta.classList.add('village-meta');
                const ownerName = village.owner || 'Barbarian';
                meta.textContent = `${ownerName} · ${village.points || 0} pts`;
                if (village.activity_bucket) {
                    const activity = document.createElement('span');
                    activity.classList.add('activity-pill');
                    activity.textContent = village.activity_bucket === 'stale' ? 'Inactive' : `Active ${village.activity_bucket}`;
                    meta.appendChild(activity);
                }
                if (village.is_protected) {
                    const prot = document.createElement('span');
                    prot.classList.add('protected-pill');
                    prot.textContent = 'Protected';
                    meta.appendChild(prot);
                }
                tile.appendChild(meta);
            } else {
                tile.classList.add('empty');
                if (village && !showVillage) {
                    tile.classList.add('filtered-out');
                }
                const freeIcon = document.createElement('img');
                freeIcon.classList.add('overlay-icon');
                freeIcon.src = overlayIcons.free;
                freeIcon.alt = 'Empty';
                tile.appendChild(freeIcon);
            }

            const coordsEl = document.createElement('div');
            coordsEl.classList.add('coords');
            coordsEl.textContent = `${coordX}|${coordY} (${formatContinent(coordX, coordY)})`;
            tile.appendChild(coordsEl);

            fragment.appendChild(tile);
        }
    }

    mapGrid.appendChild(fragment);
    const renderDuration = performance.now() - renderStart;
    maybeSendMapTelemetry(renderDuration);
}

let lastTelemetrySend = 0;
function maybeSendMapTelemetry(renderMs) {
    const now = Date.now();
    // Sample 1 in 20 renders and throttle to once per 30s.
    if (Math.random() > 0.05) return;
    if (now - lastTelemetrySend < 30000) return;
    lastTelemetrySend = now;
    fetch('../ajax/telemetry/map_perf.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            render_ms: renderMs,
            payload_bytes: mapState.lastPayloadBytes || null,
            cache_hit_pct: null,
            request_rate: null,
            dropped_frames: null
        })
    }).catch(() => {
        // ignore telemetry failures
    });
}

function updateControls() {
    const centerDisplay = document.getElementById('toolbar-center');
    if (centerDisplay) {
        centerDisplay.textContent = `${mapState.center.x}|${mapState.center.y} (${formatContinent(mapState.center.x, mapState.center.y)})`;
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
    requestMapLoad(mapState.center.x + dx * stride, mapState.center.y + dy * stride, mapState.size);
}

function formatArrivalTime(tsSeconds) {
    if (!Number.isFinite(tsSeconds)) return '';
    const d = new Date(tsSeconds * 1000);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function populateTravelUnits() {
    if (!travelUnitSelect) return;
    travelUnitSelect.innerHTML = '';
    const speeds = mapState.unitSpeeds || {};
    const entries = Object.entries(speeds);
    if (!entries.length) {
        travelUnitSelect.innerHTML = '<option value="">No unit speeds</option>';
        travelUnitSelect.disabled = true;
        return;
    }
    travelUnitSelect.disabled = false;
    const lastChoice = localStorage.getItem('map_travel_unit') || '';
    entries.forEach(([internal, data]) => {
        const option = document.createElement('option');
        option.value = internal;
        const minutes = data.minutes_per_field ? data.minutes_per_field.toFixed(1) : '';
        option.textContent = `${internal} (${minutes} min/field)`;
        if (internal === lastChoice) option.selected = true;
        travelUnitSelect.appendChild(option);
    });
    if (!travelUnitSelect.value && travelUnitSelect.options.length > 0) {
        travelUnitSelect.value = travelUnitSelect.options[0].value;
    }
}

async function triggerTravelEstimate() {
    if (!travelUnitSelect || !travelTimeDisplay || !travelDistanceDisplay || !travelError) return;
    const unit = travelUnitSelect.value;
    if (!unit) return;
    const speeds = mapState.unitSpeeds || {};
    const data = speeds[unit];
    if (!data || !data.fields_per_hour) return;
    const targetX = parseInt(travelUnitSelect.dataset.targetX || '0', 10);
    const targetY = parseInt(travelUnitSelect.dataset.targetY || '0', 10);
    travelTimeDisplay.textContent = '...';
    travelDistanceDisplay.textContent = '';
    travelError.style.display = 'none';
    try {
        const resp = await fetch(`../ajax/map/travel_time.php?target_x=${targetX}&target_y=${targetY}&unit_speed=${data.fields_per_hour}&grid_type=square&terrain_multiplier=1`);
        const json = await resp.json();
        if (json.status === 'success' || json.distance !== undefined) {
            const dist = json.distance !== undefined ? json.distance : null;
            if (dist !== null) {
                travelDistanceDisplay.textContent = `${dist.toFixed(1)} fields`;
            }
            const time = json.travel_time_formatted || (json.travel_time_seconds ? secondsToTime(json.travel_time_seconds) : null);
            if (time) {
                travelTimeDisplay.textContent = time;
            } else {
                travelTimeDisplay.textContent = '–';
            }
            localStorage.setItem('map_travel_unit', unit);
        } else {
            travelTimeDisplay.textContent = '–';
            travelError.textContent = json.error || json.message || 'Travel calc failed.';
            travelError.style.display = 'block';
        }
    } catch (e) {
        travelTimeDisplay.textContent = '–';
        travelError.textContent = 'Travel calc failed.';
        travelError.style.display = 'block';
    }
}

function jumpToHome() {
    if (homeVillage) {
        requestMapLoad(homeVillage.x, homeVillage.y, mapState.size);
    }
}

function handleJumpForm(event) {
    event.preventDefault();
    const xInput = document.getElementById('jump-x');
    const yInput = document.getElementById('jump-y');
    const targetX = parseInt(xInput.value, 10);
    const targetY = parseInt(yInput.value, 10);
    if (Number.isFinite(targetX) && Number.isFinite(targetY)) {
        requestMapLoad(targetX, targetY, mapState.size);
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
    const popupVillageDistance = document.getElementById('popup-village-distance');
    const popupVillagePoints = document.getElementById('popup-village-points');
    const travelUnitSelect = document.getElementById('travel-unit-select');
    const travelTimeDisplay = document.getElementById('travel-time-display');
    const travelDistanceDisplay = document.getElementById('travel-distance-display');
    const travelError = document.getElementById('travel-error');
    const movementSummary = document.getElementById('movement-summary');
    const popupSendUnitsButton = document.getElementById('popup-send-units');
    const popupAttackButton = document.getElementById('popup-attack');
    const popupSupportButton = document.getElementById('popup-support');
    const popupOpenVillage = document.getElementById('popup-open-village');
    const reserveToggle = document.getElementById('reserve-village');
    const reserveScope = document.getElementById('reserve-scope');
    const noteTextarea = document.getElementById('village-note');
    const annotation = getAnnotation(village.id);
    const player = village.user_id ? mapState.players[village.user_id] : null;

    const tribe = player && player.ally_id ? mapState.tribes[player.ally_id] : null;
    const ownerLabel = player ? `${tribe ? '[' + tribe.tag + '] ' : ''}${player.username}` : (village.owner || 'Barbarian village');

    popupVillageName.textContent = village.name;
    popupVillageOwner.textContent = ownerLabel;
    popupVillageCoords.textContent = `${x}|${y} (${formatContinent(x, y)})`;
    if (popupVillageDistance) {
        const distanceLabel = formatDistanceFromHome(x, y);
        popupVillageDistance.textContent = distanceLabel;
        popupVillageDistance.style.display = distanceLabel ? 'inline-block' : 'none';
    }
    popupVillagePoints.textContent = `${village.points || 0} pts${village.is_protected ? ' • Protected' : ''}`;

    popupSendUnitsButton.dataset.villageId = village.id;
    popupAttackButton.dataset.villageId = village.id;
    popupSupportButton.dataset.villageId = village.id;
    reserveToggle.dataset.villageId = village.id;
    reserveScope.dataset.villageId = village.id;
    noteTextarea.dataset.villageId = village.id;
    reserveToggle.dataset.x = x;
    reserveToggle.dataset.y = y;
    reserveScope.dataset.x = x;
    reserveScope.dataset.y = y;
    noteTextarea.dataset.x = x;
    noteTextarea.dataset.y = y;
    reserveToggle.checked = !!annotation.reserved;
    reserveScope.value = annotation.reserved || 'self';
    noteTextarea.value = annotation.note || '';
    updateNoteStatus('');

    if (village.user_id && village.user_id !== -1) {
        popupOpenVillage.style.display = 'inline-block';
        popupOpenVillage.href = `../player/player.php?id=${village.user_id}`;
    } else {
        popupOpenVillage.style.display = 'none';
    }

    if (travelUnitSelect && travelTimeDisplay && travelDistanceDisplay && travelError) {
        travelError.style.display = 'none';
        travelError.textContent = '';
        travelDistanceDisplay.textContent = '';
        travelTimeDisplay.textContent = '–';
        travelUnitSelect.dataset.targetX = x;
        travelUnitSelect.dataset.targetY = y;
        populateTravelUnits();
        triggerTravelEstimate();
    }

    if (movementSummary) {
        const summary = village.movement_summary || {};
        const { incoming = 0, outgoing = 0, support = 0, has_noble = false, earliest = null } = summary;
        const parts = [];
        parts.push(`<span class="pill incoming">Incoming: ${incoming}</span>`);
        parts.push(`<span class="pill outgoing">Outgoing: ${outgoing}</span>`);
        parts.push(`<span class="pill support">Support: ${support}</span>`);
        if (has_noble) {
            parts.push('<span class="pill noble">Noble spotted</span>');
        }
        let eta = '';
        if (earliest) {
            const etaDate = new Date(earliest * 1000);
            eta = `<div>Earliest command ETA: ${etaDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
        }
        movementSummary.innerHTML = `<div class="count-row">${parts.join('')}</div>${eta}`;
        movementSummary.style.display = 'block';
    }

    if (village.id === currentVillageId) {
        popupSendUnitsButton.style.display = 'none';
        popupAttackButton.style.display = 'none';
        popupSupportButton.style.display = 'none';
    } else {
        popupSendUnitsButton.style.display = 'block';
        popupAttackButton.style.display = 'inline-block';
        popupSupportButton.style.display = 'inline-block';
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

function renderMiniMap() {
    const canvas = document.getElementById('mini-map');
    if (!canvas || !worldBounds) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const pad = 8;
    const width = canvas.width;
    const height = canvas.height;
    ctx.clearRect(0, 0, width, height);

    const minX = worldBounds.min_x;
    const maxX = worldBounds.max_x;
    const minY = worldBounds.min_y;
    const maxY = worldBounds.max_y;
    const spanX = Math.max(1, maxX - minX);
    const spanY = Math.max(1, maxY - minY);

    ctx.fillStyle = '#f4efe6';
    ctx.fillRect(0, 0, width, height);
    ctx.strokeStyle = '#c8b79a';
    ctx.strokeRect(0.5, 0.5, width - 1, height - 1);

    const scaleX = (width - 2 * pad) / spanX;
    const scaleY = (height - 2 * pad) / spanY;

    Object.values(mapState.byCoord || {}).forEach(v => {
        const relX = (v.x - minX) * scaleX + pad;
        const relY = (v.y - minY) * scaleY + pad;
        const relation = getVillageRelationClass(v);
const relationColorsDefault = {
    'relation-own': '#2c7be5',
    'relation-ally': '#4caf50',
    'relation-nap': '#1abc9c',
    'relation-truce': '#6c757d',
    'relation-enemy': '#c0392b',
    'relation-neutral': '#d1a23d',
    'relation-barb': '#7f8c8d'
};
const relationColorsHighContrast = {
    'relation-own': '#1f6fff',
    'relation-ally': '#0ac14b',
    'relation-nap': '#04c2c9',
    'relation-truce': '#9ea7b3',
    'relation-enemy': '#ff3b30',
    'relation-neutral': '#f3c622',
    'relation-barb': '#c0c4cc'
};
        const colors = mapHighContrast ? relationColorsHighContrast : relationColorsDefault;
        ctx.fillStyle = colors[relation] || colors['relation-neutral'];
        ctx.fillRect(relX - 2, relY - 2, 4, 4);
    });

    const radius = Math.floor((mapState.size - 1) / 2);
    const viewMinX = mapState.center.x - radius;
    const viewMaxX = mapState.center.x + radius;
    const viewMinY = mapState.center.y - radius;
    const viewMaxY = mapState.center.y + radius;

    const rectX = (viewMinX - minX) * scaleX + pad;
    const rectY = (viewMinY - minY) * scaleY + pad;
    const rectW = (viewMaxX - viewMinX) * scaleX;
    const rectH = (viewMaxY - viewMinY) * scaleY;

    ctx.strokeStyle = '#2c7be5';
    ctx.lineWidth = 2;
    ctx.strokeRect(rectX, rectY, rectW, rectH);
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

.map-subtoolbar {
    margin: 10px 0 6px 0;
}

.filter-card {
    background: var(--panel-bg);
    border: 1px solid var(--panel-border);
    border-radius: 10px;
    padding: 10px 12px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.filter-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    font-size: 13px;
    color: #4a3c30;
}

.secondary-filters {
    margin-top: 6px;
    color: #5c4735;
    font-size: 12px;
}

.filter-hint {
    font-size: 12px;
    color: #7a6347;
    margin-top: 6px;
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
    position: relative;
}

.map-grid {
    display: grid;
    gap: 1px;
    background: #c6b28f;
    border: 1px solid var(--panel-border);
    border-radius: 8px;
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

.map-grid.is-loading .map-tile {
    background: linear-gradient(90deg, #e3dacb 25%, #f2ece1 50%, #e3dacb 75%);
    background-size: 200% 100%;
    animation: map-skeleton 1.2s ease-in-out infinite;
}

.map-tile:hover {
    outline: 1px solid #d19b3a;
    z-index: 2;
}

.map-tile.reserved {
    box-shadow: inset 0 0 0 2px rgba(162, 106, 49, 0.4);
}
.map-tile.protected {
    box-shadow: inset 0 0 0 2px rgba(29, 110, 216, 0.35);
}

.map-tile.filtered-out {
    opacity: 0.45;
}

body.map-high-contrast .map-grid {
    background: #0f172a;
    border-color: #1f2937;
}
body.map-high-contrast .map-tile {
    outline: 1px solid rgba(0,0,0,0.25);
}
body.map-high-contrast .map-tile.relation-own { box-shadow: inset 0 0 0 2px #1f6fff; }
body.map-high-contrast .map-tile.relation-ally { box-shadow: inset 0 0 0 2px #0ac14b; }
body.map-high-contrast .map-tile.relation-nap { box-shadow: inset 0 0 0 2px #04c2c9; }
body.map-high-contrast .map-tile.relation-truce { box-shadow: inset 0 0 0 2px #9ea7b3; }
body.map-high-contrast .map-tile.relation-enemy { box-shadow: inset 0 0 0 2px #ff3b30; }
body.map-high-contrast .map-tile.relation-neutral { box-shadow: inset 0 0 0 2px #f3c622; }
body.map-high-contrast .map-tile.relation-barb { box-shadow: inset 0 0 0 2px #c0c4cc; }
body.map-high-contrast .coords {
    background: rgba(10,10,10,0.82);
    color: #fff;
}
body.map-high-contrast .village-label {
    background: rgba(10,10,10,0.78);
    color: #fff;
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

.movement-stack {
    position: absolute;
    left: 4px;
    bottom: 4px;
    display: flex;
    gap: 2px;
}

.movement-icon {
    width: 14px;
    height: 14px;
}

body.map-reduced-motion .map-tile,
body.map-reduced-motion .village-label,
body.map-reduced-motion .coords {
    transition: none;
}

.map-skeleton {
    position: absolute;
    inset: 0;
    pointer-events: none;
    display: none;
    background: rgba(255, 252, 247, 0.7);
    z-index: 2;
}

.map-skeleton .skeleton-row {
    height: 28px;
    margin: 6px 12px;
    border-radius: 8px;
    background: linear-gradient(90deg, #e6ddcf 25%, #f2ebe1 50%, #e6ddcf 75%);
    background-size: 200% 100%;
    animation: map-skeleton 1.2s ease-in-out infinite;
}

body.map-reduced-motion .map-grid.is-loading .map-tile,
body.map-reduced-motion .map-skeleton .skeleton-row {
    animation: none;
}

.note-icon {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 16px;
    height: 16px;
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

.legend-dot {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid rgba(0,0,0,0.4);
    background: #c6b28f;
}
.legend-dot.relation-own { background: #2c7be5; }
.legend-dot.relation-ally { background: #4caf50; }
.legend-dot.relation-nap { background: #1abc9c; }
.legend-dot.relation-truce { background: #6c757d; }
.legend-dot.relation-enemy { background: #c0392b; }
.legend-dot.relation-neutral { background: #d1a23d; }
.legend-dot.relation-barb { background: #7f8c8d; }

body.map-high-contrast .legend-dot.relation-own { background: #1f6fff; border-color: #0d3d96; }
body.map-high-contrast .legend-dot.relation-ally { background: #0ac14b; border-color: #036d2c; }
body.map-high-contrast .legend-dot.relation-nap { background: #04c2c9; border-color: #006b70; }
body.map-high-contrast .legend-dot.relation-truce { background: #9ea7b3; border-color: #4a5568; }
body.map-high-contrast .legend-dot.relation-enemy { background: #ff3b30; border-color: #a2160f; }
body.map-high-contrast .legend-dot.relation-neutral { background: #f3c622; border-color: #a67700; }
body.map-high-contrast .legend-dot.relation-barb { background: #c0c4cc; border-color: #6e7683; }

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
.distance-pill { background: #eef1ff; border-color: #c3c8f7; color: #1f2a44; }
.protected-pill {
    margin-left: 6px;
    padding: 2px 6px;
    background: #e6f4ff;
    border: 1px solid #9ac8ff;
    color: #1d6ed8;
    border-radius: 8px;
    font-size: 11px;
}

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

.note-card {
    background: #fff;
    border: 1px solid #e3caa3;
    border-radius: 8px;
    padding: 8px 10px;
    margin-top: 10px;
}

.note-card textarea {
    width: 100%;
    resize: vertical;
    border-radius: 6px;
    border: 1px solid #d6b985;
    padding: 6px;
}

.note-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}

.note-status {
    font-size: 12px;
    color: #7a6347;
}

.reserve-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #6a4a1f;
}

.map-skeleton {
    position: absolute;
    inset: 0;
    pointer-events: none;
    display: none;
    background: rgba(255, 252, 247, 0.7);
    z-index: 2;
}

.map-skeleton .skeleton-row {
    height: 28px;
    margin: 6px 12px;
    border-radius: 8px;
    background: linear-gradient(90deg, #e6ddcf 25%, #f2ebe1 50%, #e6ddcf 75%);
    background-size: 200% 100%;
    animation: map-skeleton 1.2s ease-in-out infinite;
}

.map-loading {
    position: absolute;
    top: 12px;
    left: 12px;
    display: none;
    align-items: center;
    gap: 8px;
    background: rgba(255, 249, 237, 0.9);
    border: 1px solid #d8b986;
    border-radius: 10px;
    padding: 6px 10px;
    z-index: 3;
    font-weight: 700;
    color: #5b3a12;
}

.map-loading .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(139, 91, 44, 0.35);
    border-left-color: #8b5b2c;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

body.map-reduced-motion .map-loading .spinner {
    animation: none;
}

.map-grid.is-loading .map-tile {
    background: linear-gradient(90deg, #e3dacb 25%, #f2ece1 50%, #e3dacb 75%);
    background-size: 200% 100%;
    animation: map-skeleton 1.2s ease-in-out infinite;
}

body.map-reduced-motion .map-grid.is-loading .map-tile {
    animation: none;
}

@keyframes map-skeleton {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@keyframes spin {
    to { transform: rotate(360deg); }
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
