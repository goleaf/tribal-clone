document.addEventListener('DOMContentLoaded', () => {
    const listEl = document.getElementById('guide-list');
    const listViewEl = document.getElementById('guide-list-view');
    const detailEl = document.getElementById('guide-detail');
    const detailTitleEl = document.getElementById('guide-detail-title');
    const detailMetaEl = document.getElementById('guide-detail-meta');
    const detailBodyEl = document.getElementById('guide-detail-body');
    const searchInput = document.getElementById('guide-search');
    const searchBtn = document.getElementById('guide-search-btn');
    const categorySelect = document.getElementById('guide-category');
    const tagsInput = document.getElementById('guide-tags');
    const resetBtn = document.getElementById('guide-reset');
    const backBtn = document.getElementById('guide-back');
    const guidesCount = document.getElementById('guides-count');

    // Search on Enter key
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            fetchGuides();
        }
    });

    // Quick links
    document.querySelectorAll('.quick-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const category = link.dataset.category;
            if (category) {
                categorySelect.value = category;
                fetchGuides();
            }
        });
    });

    async function fetchGuides() {
        // Show loading state
        listEl.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading guides...</p>
            </div>
        `;

        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const category = categorySelect.value.trim();
        const tags = tagsInput.value.trim();
        
        if (q) params.set('q', q);
        if (category) params.set('category', category);
        if (tags) params.set('tags', tags);
        params.set('limit', '30');

        try {
            const res = await fetch(`/ajax/guides/list.php?${params.toString()}`);
            const data = await res.json();
            
            if (data.status !== 'success') {
                listEl.innerHTML = '<div class="guide-empty">❌ Unable to load guides. Please try again.</div>';
                guidesCount.textContent = '0 guides';
                return;
            }
            
            renderGuides(data.data.items || []);
        } catch (error) {
            listEl.innerHTML = '<div class="guide-empty">❌ Network error. Please check your connection.</div>';
            guidesCount.textContent = '0 guides';
        }
    }

    function renderGuides(guides) {
        // Hide detail view and show list
        detailEl.hidden = true;
        listViewEl.style.display = 'block';

        // Update count
        const count = guides.length;
        guidesCount.textContent = `${count} guide${count !== 1 ? 's' : ''}`;

        if (!guides.length) {
            listEl.innerHTML = `
                <div class="guide-empty">
                    <p style="font-size: 48px; margin-bottom: 16px;">📚</p>
                    <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No guides found</p>
                    <p>Try adjusting your search or filters</p>
                </div>
            `;
            return;
        }

        listEl.innerHTML = guides.map(g => {
            const tags = (g.tags || '').split(',').filter(Boolean).map(tag => 
                `<span class="guide-tag">${escapeHtml(tag.trim())}</span>`
            ).join('');
            
            const date = g.updated_at ? new Date(g.updated_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) : '';

            return `
                <article class="guide-card" data-slug="${escapeHtml(g.slug)}">
                    <h3>${escapeHtml(g.title)}</h3>
                    <p class="muted">${escapeHtml(g.summary)}</p>
                    ${tags ? `<div class="guide-tags">${tags}</div>` : ''}
                    ${date ? `<small>📅 Updated ${date}</small>` : ''}
                </article>
            `;
        }).join('');
    }

    async function loadGuide(slug) {
        // Show loading in detail view
        detailEl.hidden = false;
        listViewEl.style.display = 'none';
        detailBodyEl.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading guide...</p>
            </div>
        `;

        try {
            const res = await fetch(`/ajax/guides/detail.php?slug=${encodeURIComponent(slug)}`);
            const data = await res.json();
            
            if (data.status !== 'success') {
                detailBodyEl.innerHTML = '<div class="guide-empty">❌ Unable to load guide.</div>';
                return;
            }

            const guide = data.data;
            detailTitleEl.textContent = guide.title;
            
            const date = guide.updated_at ? new Date(guide.updated_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) : '';
            
            const categoryEmoji = {
                'basics': '🎯',
                'economy': '💰',
                'combat': '⚔️',
                'map': '🗺️',
                'tribe': '👥'
            };
            
            const emoji = categoryEmoji[guide.category] || '📖';
            detailMetaEl.textContent = `${emoji} ${guide.category || 'General'} • Updated ${date}`;
            detailBodyEl.innerHTML = guide.body_html;
            
            // Scroll to top of detail view
            detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            detailBodyEl.innerHTML = '<div class="guide-empty">❌ Network error. Please try again.</div>';
        }
    }

    // Event delegation for guide cards
    listEl.addEventListener('click', (event) => {
        const card = event.target.closest('.guide-card');
        if (card && card.dataset.slug) {
            loadGuide(card.dataset.slug);
        }
    });

    // Search button
    searchBtn.addEventListener('click', fetchGuides);

    // Reset button
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        categorySelect.value = '';
        tagsInput.value = '';
        fetchGuides();
    });

    // Back button
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            detailEl.hidden = true;
            listViewEl.style.display = 'block';
            listViewEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // Category select change
    categorySelect.addEventListener('change', fetchGuides);

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initial load
    fetchGuides();
});
