document.addEventListener('DOMContentLoaded', () => {
    const listEl = document.getElementById('guide-list');
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

    async function fetchGuides() {
        const params = new URLSearchParams();
        const q = searchInput.value.trim();
        const category = categorySelect.value.trim();
        const tags = tagsInput.value.trim();
        if (q) params.set('q', q);
        if (category) params.set('category', category);
        if (tags) params.set('tags', tags);
        params.set('limit', '30');
        const res = await fetch(`/ajax/guides/list.php?${params.toString()}`);
        const data = await res.json();
        if (data.status !== 'success') {
            listEl.innerHTML = '<div class="guide-empty">Unable to load guides.</div>';
            return;
        }
        renderGuides(data.data.items || []);
    }

    function renderGuides(guides) {
        detailEl.hidden = true;
        if (!guides.length) {
            listEl.innerHTML = '<div class="guide-empty">No guides found. Try another search.</div>';
            return;
        }
        listEl.innerHTML = guides.map(g => {
            const tags = (g.tags || '').split(',').filter(Boolean).map(tag => `<span class="guide-tag">${tag}</span>`).join('');
            const date = g.updated_at ? new Date(g.updated_at).toLocaleDateString() : '';
            return `<article class="guide-card" data-slug="${g.slug}">
                <h3>${g.title}</h3>
                <p class="muted">${g.summary}</p>
                <div class="guide-tags">${tags}</div>
                <small>${date}</small>
            </article>`;
        }).join('');
    }

    async function loadGuide(slug) {
        const res = await fetch(`/ajax/guides/detail.php?slug=${encodeURIComponent(slug)}`);
        const data = await res.json();
        if (data.status !== 'success') {
            detailEl.hidden = true;
            return;
        }
        const guide = data.data;
        detailTitleEl.textContent = guide.title;
        const date = guide.updated_at ? new Date(guide.updated_at).toLocaleString() : '';
        detailMetaEl.textContent = `${guide.category || 'general'} • ${date}`;
        detailBodyEl.innerHTML = guide.body_html;
        detailEl.hidden = false;
        detailEl.scrollIntoView({ behavior: 'smooth' });
    }

    listEl.addEventListener('click', (event) => {
        const card = event.target.closest('.guide-card');
        if (card && card.dataset.slug) {
            loadGuide(card.dataset.slug);
        }
    });

    searchBtn.addEventListener('click', fetchGuides);
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        categorySelect.value = '';
        tagsInput.value = '';
        fetchGuides();
    });
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            detailEl.hidden = true;
            listEl.scrollIntoView({ behavior: 'smooth' });
        });
    }

    fetchGuides();
});
