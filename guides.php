<?php
require 'init.php';
require_once __DIR__ . '/lib/managers/GuideManager.php';

$pageTitle = 'Guides';
$guideManager = new GuideManager($conn);
$guideManager->ensureSchema();
$guideManager->seedDefaults($_SESSION['user_id'] ?? null);

require 'header.php';
?>
<main class="container guides-container">
    <div class="guides-hero">
        <div>
            <p class="eyebrow">Guides</p>
            <h1>Learn the world of Tribes</h1>
            <p class="muted">Battle primers, economy starts, scouting basics and more—curated in-game.</p>
        </div>
        <div class="guides-search">
            <input type="text" id="guide-search" placeholder="Search guides..." aria-label="Search guides">
            <button class="btn" id="guide-search-btn">Search</button>
        </div>
    </div>

    <section class="guides-layout">
        <aside class="guides-filters">
            <div class="panel">
                <h3>Filters</h3>
                <label class="filter-label">Category
                    <select id="guide-category">
                        <option value="">All</option>
                        <option value="basics">Basics</option>
                        <option value="economy">Economy</option>
                        <option value="combat">Combat</option>
                        <option value="map">Map</option>
                        <option value="tribe">Tribe</option>
                    </select>
                </label>
                <label class="filter-label">Tags
                    <input type="text" id="guide-tags" placeholder="e.g. wall,scout">
                </label>
                <button class="btn" id="guide-reset">Reset</button>
            </div>
        </aside>

        <section class="guides-content">
            <div id="guide-list" class="guide-list" aria-live="polite"></div>
            <article id="guide-detail" class="guide-detail" hidden>
                <button class="text-button" id="guide-back">&larr; Back to guides</button>
                <h2 id="guide-detail-title"></h2>
                <p class="muted" id="guide-detail-meta"></p>
                <div id="guide-detail-body"></div>
            </article>
        </section>
    </section>
</main>

<script src="/js/guides.js" defer></script>
<?php require 'footer.php'; ?>
