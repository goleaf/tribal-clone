# Guides section plan

Goal: ship an in-game Guides section that mirrors the forum guides spirit (tutorials, tips, configs) without external dependencies. Priorities: easy discovery, safe publishing/moderation, and fast iteration on content.

## Assumptions
- Content is stored locally (DB + markdown/HTML) and exposed in `/help.php` (or a new `/guides.php`) for logged-in users; no runtime forum scraping.
- Editors are admin/staff roles only; players can suggest topics via messages or a future feedback form.
- Support both desktop and mobile; reuse existing layout components (panels, cards, popups).

## Milestones
### M1: Foundation
- DB: `guides` table (id, slug, title, summary, body_html, tags, category, status draft/published/archived, author_id, reviewer_id, created_at, updated_at, version, locale). Optional `guide_revisions` for history.
- Backend: Manager class with CRUD, slugs, publication workflow, tag parsing, search (title/summary/tags/body), and pagination.
- AJAX endpoints: list, detail, search/filter, publish/archive (admin only).
- UI: `/guides.php` page with filters (category, tag, search), cards, quick stats; detail view modal/page with breadcrumb, tags, last updated, author.
- Auth: only published guides visible to players; draft/archived gated to staff.
- Tests: manager unit tests (create/update/publish/search), endpoint auth tests, and a fixture seeding 2–3 sample guides.

### M2: Integration + UX
- Cross-links: surface contextual "Read guide" buttons in building/info panels (barracks, market, smithy, wall, map attack modal). Map: add "Guides" entry in top nav/help.
- Related content: show 3 related guides by tag/category at end of detail.
- Offline-friendly: cache busting via `version` and `updated_at`; return ETag/Last-Modified; max-age headers.
- Editor UX (admin): simple form (title, summary, tags CSV, category select, body textarea/markdown-to-HTML), preview, publish, archive, revision diff.
- Analytics: track basic views (guide_id, user_id nullable, created_at) into `guide_views` table for popularity sorting.
- Tests: cross-link availability, auth on admin actions, popularity ordering, cache headers.

### M3: Content + Quality
- Seed content: migrate key how-to articles (village start, economy basics, troop comps, scouting, wall/ram/catapult mechanics, map/attack primer). Use markdown stored in repo for initial load.
- Quality gates: required summary, tag count 1–5, min body length, and link sanitizer for external URLs.
- Localized stubs: allow locale column for future translations; fallback to `en` if missing.
- Feedback loop: add lightweight "Was this helpful?" (up/down) stored in `guide_feedback` with per-user throttling.
- Tests: content sanitizer, feedback throttling, localization fallback.

## Data model (draft)
- `guides`: id, slug, title, summary, body_html, tags (comma list), category, status, author_id, reviewer_id, version, locale, created_at, updated_at.
- `guide_revisions`: id, guide_id, version, editor_id, body_html, summary, tags, created_at.
- `guide_views`: id, guide_id, user_id nullable, created_at.
- `guide_feedback`: id, guide_id, user_id nullable, vote (+1/-1), created_at.

## Implementation notes
- Use existing `NotificationManager` patterns for CRUD and auth guards; mirror AJAX response shapes used by buildings/units endpoints.
- Keep body in HTML to render safely in PHP; sanitize input (allow basic tags, strip scripts/styles).
- Slugs: derive from title, ensure uniqueness; 301 redirect old slugs when version increments (optional).
- UI components: reuse panel/card styles from `css/main.css`; add a compact list for mobile; support deep links `/guides.php?slug=...`.
- Performance: index guides on (status, category), (tags), and full-text (title, summary, body) where supported; add pagination defaults (e.g., 20 per page).

## Deliverables per milestone
- M1: DB migrations + manager + endpoints + `/guides.php` list/detail + tests + sample seed.
- M2: Contextual links in UI, admin editor, analytics, cache headers + tests.
- M3: Seeded guides set, feedback, localization-ready fields, sanitizer hardening + tests.
