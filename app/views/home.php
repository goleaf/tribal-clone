<main class="homepage">
    <section class="hero" style="background-image: url('/img/village_bg.jpg');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h2>Welcome to the new version of Tribal Wars!</h2>
            <p>Discover a modern take on Tribal Wars with dynamic gameplay and strategic challenges.</p>
            <div class="hero-buttons">
                <a href="/auth/register.php" class="btn btn-primary">Register</a>
                <a href="/auth/login.php" class="btn btn-secondary">Log in</a>
            </div>
        </div>
    </section>
    
    <section class="stats-bar">
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['worlds'] ?? 0) ?></span>
            <span class="stat-label">Worlds</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['players'] ?? 0) ?></span>
            <span class="stat-label">Players</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?= number_format($stats['villages'] ?? 0) ?></span>
            <span class="stat-label">Villages</span>
        </div>
    </section>
    
    <section class="features">
        <h2>Key Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/img/ds_graphic/resources.png" alt="Resources">
                </div>
                <h3>Resource Production</h3>
                <p>Manage the extraction of wood, clay, and iron in real time. Expand your resource sites to boost production.</p>
                <a href="/auth/register.php" class="feature-link">Start Producing</a>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/img/ds_graphic/buildings/main.png" alt="Buildings">
                </div>
                <h3>Village Development</h3>
                <p>Build and upgrade structures to strengthen your position. Each building unlocks new options and advantages.</p>
                <a href="/auth/register.php" class="feature-link">Build Your Empire</a>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/img/ds_graphic/map/map.png" alt="World Map">
                </div>
                <h3>Interactive Map</h3>
                <p>Explore a draggable world map. Plan strategic attacks and conquer new territories.</p>
                <a href="/auth/register.php" class="feature-link">Explore the World</a>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <img src="/img/ds_graphic/unit/spear.png" alt="Units">
                </div>
                <h3>Combat Units</h3>
                <p>Recruit different unit types and attack enemies. Build powerful armies and defend your territory.</p>
                <a href="/auth/register.php" class="feature-link">Build an Army</a>
            </div>
        </div>
    </section>
    
    <section class="game-description">
        <div class="description-container">
            <h2>Strategic Browser Game</h2>
            <p>Tribal Wars is a strategy game where, as the leader of a small village, you must grow your territory, gather resources, and build an army. Forge alliances with other players, conquer new lands, and become the most powerful ruler in the world of Tribes!</p>
            
            <h3>Core gameplay elements:</h3>
            <ul class="feature-list">
                <li><strong>Economic growth</strong> - build resource sites and increase production</li>
                <li><strong>Territorial expansion</strong> - establish new villages and grow your empire</li>
                <li><strong>Military</strong> - train varied units and wage wars</li>
                <li><strong>Diplomacy</strong> - forge alliances and cooperate with other players</li>
            </ul>
            
            <div class="cta-box">
                <h3>Join the game now!</h3>
                <p>Registration takes just a moment, and the game is completely free.</p>
                <a href="/auth/register.php" class="btn btn-primary">Start Playing</a>
            </div>
        </div>
    </section>
</main>
