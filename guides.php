<?php
// Public guides page - no authentication required
$isPublicPage = true;
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/managers/GuideManager.php';

$pageTitle = 'Game Guides - Tribal Wars Clone';
$conn = getDBConnection();
$guideManager = new GuideManager($conn);
$guideManager->ensureSchema();
$guideManager->seedDefaults(null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/guides-public.css">
    <link rel="icon" href="/favicon.ico">
</head>
<body class="guides-public-page">
    <!-- Navigation Component -->
    <nav class="public-nav">
        <div class="nav-container">
            <a href="/" class="nav-brand">
                <span class="brand-icon">⚔️</span>
                <span class="brand-text">Tribal Wars</span>
            </a>
            <div class="nav-links">
                <a href="/guides.php" class="nav-link active">Guides</a>
                <a href="/help.php" class="nav-link">Help</a>
                <a href="/terms.php" class="nav-link">Terms</a>
                <a href="/auth/login.php" class="nav-link nav-link-primary">Login</a>
                <a href="/auth/register.php" class="nav-btn">Play Now</a>
            </div>
            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Component -->
    <header class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <span class="hero-badge">📚 Knowledge Base</span>
                <h1 class="hero-title">Master the Art of War</h1>
                <p class="hero-subtitle">From village basics to advanced combat tactics—everything you need to dominate the battlefield.</p>
            </div>
            
            <!-- Search Component -->
            <div class="search-component">
                <div class="search-wrapper">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                        <circle cx="8" cy="8" r="6" stroke-width="2"/>
                        <path d="M13 13l5 5" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input type="text" id="guide-search" class="search-input" placeholder="Search guides..." aria-label="Search guides">
                </div>
                <button class="btn-primary" id="guide-search-btn">Search</button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-container">
            <!-- Sidebar Component -->
            <aside class="sidebar-component">
                <!-- Filter Card Component -->
                <div class="card filter-card">
                    <div class="card-header">
                        <svg class="card-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M1 3h14M3 8h10M6 13h4" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                        <h3 class="card-title">Filters</h3>
                    </div>
                    
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select id="guide-category" class="form-select">
                                <option value="">All Categories</option>
                                <option value="basics">🎯 Basics</option>
                                <option value="economy">💰 Economy</option>
                                <option value="combat">⚔️ Combat</option>
                                <option value="map">🗺️ Map & Travel</option>
                                <option value="tribe">👥 Tribe</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tags</label>
                            <input type="text" id="guide-tags" class="form-input" placeholder="e.g. wall, scout">
                        </div>

                        <button class="btn-secondary btn-block" id="guide-reset">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor">
                                <path d="M12 2L2 12M2 2l10 10" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            Reset Filters
                        </button>
                    </div>
                </div>

                <!-- Quick Links Card Component -->
                <div class="card quick-links-card">
                    <div class="card-header">
                        <h4 class="card-title">Quick Access</h4>
                    </div>
                    <div class="card-body">
                        <a href="#" class="quick-link" data-category="basics">
                            <span class="quick-link-icon">🎯</span>
                            <span>Getting Started</span>
                        </a>
                        <a href="#" class="quick-link" data-category="economy">
                            <span class="quick-link-icon">💰</span>
                            <span>Resource Management</span>
                        </a>
                        <a href="#" class="quick-link" data-category="combat">
                            <span class="quick-link-icon">⚔️</span>
                            <span>Battle Tactics</span>
                        </a>
                        <a href="#" class="quick-link" data-category="tribe">
                            <span class="quick-link-icon">👥</span>
                            <span>Tribe Strategies</span>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- Content Area Component -->
            <section class="content-area">
                <!-- Guide List Component -->
                <div id="guide-list-view" class="guide-list-component">
                    <div class="section-header">
                        <h2 class="section-title">All Guides</h2>
                        <span class="badge" id="guides-count">Loading...</span>
                    </div>
                    <div id="guide-list" class="guide-grid" aria-live="polite">
                        <!-- Loading Component -->
                        <div class="loading-component">
                            <div class="spinner"></div>
                            <p>Loading guides...</p>
                        </div>
                    </div>
                </div>

                <!-- Guide Detail Component -->
                <article id="guide-detail" class="guide-detail-component" hidden>
                    <button class="btn-back" id="guide-back">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                            <path d="M12 4l-8 8 8 8" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Back to Guides</span>
                    </button>
                    
                    <div class="guide-detail-content">
                        <div class="guide-meta" id="guide-detail-meta"></div>
                        <h1 class="guide-title" id="guide-detail-title"></h1>
                        <div class="guide-body" id="guide-detail-body"></div>
                    </div>
                </article>
            </section>
        </div>
    </main>

    <!-- Footer Component -->
    <footer class="public-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4 class="footer-title">Tribal Wars</h4>
                    <p class="footer-text">Build your village, forge alliances, and conquer the world.</p>
                </div>
                <div class="footer-section">
                    <h4 class="footer-title">Resources</h4>
                    <a href="/guides.php" class="footer-link">Guides</a>
                    <a href="/help.php" class="footer-link">Help Center</a>
                    <a href="/terms.php" class="footer-link">Terms of Service</a>
                </div>
                <div class="footer-section">
                    <h4 class="footer-title">Community</h4>
                    <a href="#" class="footer-link">Forum</a>
                    <a href="#" class="footer-link">Discord</a>
                    <a href="#" class="footer-link">Wiki</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Tribal Wars Clone. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="/js/guides-public.js" defer></script>
</body>
</html>
