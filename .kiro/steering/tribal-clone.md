# Tribal Clone steering

Context: PHP 8.4+ browser game (Tribal Wars–style) with SQLite/MySQL, zero framework, server-rendered WAP-style UI, and minimal JS for polling/queues. Core domains: resources, buildings, units/recruitment, research, combat/reports, map, messaging, tribes, notifications.

Working rules:
- Preserve WAP/minimalist HTML: text tables, meta-refresh timers, no heavy assets; keep CSS/JS changes small and compatible with low-end devices.
- Respect domain specs in `.kiro/specs/` (e.g., `resource-system`) and docs in `docs/`, `*.md`; map changes to requirement IDs and correctness properties when possible.
- Data safety first: use prepared statements, validate ownership/CSRF for AJAX, avoid leaking errors to players, and keep migrations consistent for SQLite/MySQL.
- Keep queues accurate: building/recruit/research/attack timers must deduct resources once, clamp by warehouse/farm, and update levels/units immediately when complete.
- Tests: prefer PHPUnit/property tests where feasible; add focused regression coverage in `tests/` for new edge cases; outline manual checks when tests are impractical.
- Config awareness: default DB path in `config/config.php`, world speed/main-building speed constants, and env hints (`.env.example`) should not regress.

Communication style: brief, action-oriented summaries that cite touched files and suggest next verification steps (tests, DB migrations, manual flows).
