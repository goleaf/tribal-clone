# Feature Guardrails & Best Practices

Scope: Tribal clone PHP8.4 browser game, zero-framework, WAP-minimal UI, SQLite/MySQL dual support. Use prepared statements, CSRF validation, ownership checks, and log without leaking secrets.

- Resources & production: compute server-side using cached configs; clamp to warehouse caps after offline catch-up; never double-deduct; text-only counters `[Resource]: amount (+rate/hr)`.
- Buildings & queues: enforce HQ/main-building gate; deduct on enqueue; queue states `pending/active/completed` only; idempotent completion that sets target level; promote next pending; keep demolition refunds transactional.
- Recruitment & population: check building/research requirements and farm capacity; deduct resources+pop once; queue math monotonic; ensure unit caches stay in sync with `village_units`.
- Research: prerequisite validation against buildings/research tree; deduct immediately; update `village_research` on completion; delete/cancel cleanly with refunds when applicable.
- Combat, reports, conquest: honor RPS modifiers, wall bonuses, siege effects; victory required for allegiance drop (20–35) with cooldown/anti-snipe; preserve buildings/units on ownership transfer; generate complete reports and notifications.
- Trade & market: cap merchants by building level; travel time via distance/speed; protect against resource pushing (fair-rate bounds, point delta limits); handle return legs; audit offers/routes.
- Messaging & notifications: sanitize subject/body, enforce ownership/visibility, paginate tabs; mark read/archive with safety; rate-limit to avoid spam; emit reports/notifications for key actions.
- Tribes & diplomacy: role-based permissions (invites/kicks/announcements), treaty/war state machines with cooldowns, war score tracking, reputation/abuse checks; update tribe rankings and intel safely.
- Map & movement: distance via coords, travel time via slowest unit; respect world/map bounds; show incoming/outgoing text entries; block attacks on protected/newbie targets.
- Jobs & cron: processors must be idempotent and safe to rerun; batch queries, avoid N+1; log timing/errors; respect world settings and inactivity rules (e.g., barbarian conversion).
- Security & performance: prepared statements everywhere, CSRF on mutating routes, session/sitter validation; prefer cached managers; keep HTML minimal and mobile-first; avoid heavy JS/CSS additions.
