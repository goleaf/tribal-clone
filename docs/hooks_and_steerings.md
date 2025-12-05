# Hooks and Steerings

This project now exposes a small hook bus for instrumentation and side effects that should stay decoupled from core managers. Use it to log, emit webhooks, or fan out features without threading new dependencies everywhere.

## Hook Bus Primer
- Class: `lib/hooks/HookBus.php`, autoloaded and bootstrapped via `hooks/bootstrap.php` from `init.php`.
- API: `HookBus::addListener($event, $callable, $priority = 10)` and `HookBus::dispatch($event, $payload = [])`.
- Logging: `HookBus::logEvent($event, $payload)` appends to `logs/hooks.log` (best-effort, non-fatal).
- Default listeners: `hooks/bootstrap.php` logs core lifecycle events so you can tail `logs/hooks.log` in any environment.

## Event Catalog (payload keys)
- `village.building.completed` — `user_id`, `village_id`, `queue_id`, `building_internal`, `building_name`, `level`, `is_demolition`
- `village.recruitment.completed` — `user_id`, `village_id`, `queue_id`, `unit_type_id`, `unit_name`, `count`, `produced_now`
- `village.research.completed` — `user_id`, `village_id`, `research_internal`, `research_name`, `level`
- `village.trade.delivered` — `route_id`, `source_village_id`, `target_village_id`, `wood`, `clay`, `iron`, `arrival_time`
- `village.created` — `user_id`, `village_id`, `world_id`, `coords`
- `message.sent` — `message_id`, `sender_id`, `receiver_id`, `subject`, `body_length`

## Steering Guardrails
- Respect the queue lifecycle: resource updates first, then validation, enqueue, process, mark completed. Use the managers (`BuildingQueueManager`, `UnitManager::processRecruitmentQueue`, `ResearchManager::processResearchQueue`, `TradeManager::processArrivedTradesForVillage`) instead of ad-hoc writes.
- Keep actions idempotent: always filter by status, set target levels (not incremental math), and short-circuit if already completed.
- Protect resources: recalc offline production before spending, enforce warehouse/farm caps, and apply refunds only inside transactions.
- Persist feedback: pair state changes with `NotificationManager` and `ReportManager` entries so players see what happened; hook events above give another extension point.
- Update scoring: call `PointsManager` after building/resource-affecting changes; let `AchievementManager` evaluate progress where applicable.
- Observe and audit: add lightweight listeners in `hooks/bootstrap.php` for telemetry, anti-abuse checks, or webhooks without touching game logic.
- Security basics: keep CSRF validation on mutating routes, prefer prepared statements, and reuse `SittingManager`/session checks rather than duplicating auth logic.
- World awareness: thread `CURRENT_WORLD_ID` through new queries and configs; avoid hard-coding world 1 defaults.
- Performance hygiene: reuse cached config managers, avoid per-loop queries, and batch fetches where possible (see `BuildingConfigManager`, `UnitManager` caches).
- Testing and migrations: add migrations for schema changes, wire new queue states into `tests/`, and document run steps in `docs/` alongside feature notes.
