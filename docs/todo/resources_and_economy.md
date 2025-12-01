# Resources & Economy Design — Medieval Tribal War Browser MMO

## Resource Types
- **Core Resources:**
  - **Wood:** Timber for buildings, siege frames, mantlets. Pros: abundant early; Cons: heavy transport weight.
  - **Clay:** Bricks and mortar for walls/storage. Pros: key for defenses; Cons: mid-game scarcity if overbuilding walls.
  - **Iron:** Weapons/armor; siege heads. Pros: critical for advanced troops; Cons: slower production; high demand late-game.
  - **Food/Population (Farm Capacity):** Soft cap; consumed by troops/buildings. Pros: controls army size; Cons: over-cap penalties.
- **Advanced:**
  - **Coins/Seals/Standards:** Minted at Hall/Academy for Standard Bearers (conquest). Pros: strategic sink; Cons: expensive.
  - **Tribe Tokens/Prestige:** Earned via tribe quests/ops; used for tribe tech/cosmetics. Pros: collective progression; Cons: limited earn rate.
  - **Event Tokens:** Limited-time currency for event shops; pros: targeted rewards; cons: expire.
  - **Relic Shards (if enabled):** Combine for buffs/wonder; pros: endgame leverage; cons: contested.

## Resource Generation (Per Type)
- **Buildings:**
  - Lumber Yard, Clay Pit, Iron Mine with upgradeable rates; Farm/Granary for pop cap and food.
  - Tribe bonuses (small %) unlocked via tribe tech/quests.
- **Farming/Barbarian Raids:** Plunder resources; barb growth scales with world age; diminishing returns to avoid infinite farm.
- **PvP Raids:** Loot from players; affected by vault protection and plunder caps.
- **Quests/Achievements:** Daily/weekly tasks grant modest bundles; capped to avoid power creep.
- **Events:** Harvest festivals (boost production), caravan escorts (drop tokens/resources), raider incursions (loot on defense/offense).
- **Map Objectives:** Resource-rich hamlets, caravans, relic shrines with trickle income; roads/rivers granting transport efficiency.
- **Trading:** Market offers; buy low/sell high; arbitrage on shortages/surpluses.
- **Tribe Aid:** Internal transfers; taxed by distance/power delta.
- **Login/Return Gifts (Fair):** Small, capped bundles to help retention; no large infusions.

## Resource Sinks (Extensive)
- **Buildings:** Upgrades for production, storage, military, walls, watchtowers, hall of banners, hospitals, markets.
- **Units:** Training costs for all troops; siege and conquest units especially expensive in iron/coins/pop.
- **Tech/Research:** Unlocks (mantlets, deep scouts, wardens, rangers), efficiency upgrades.
- **Conquest Costs:** Minting coins/standards; training Standard Bearers; relocation fees.
- **Tribe Projects:** Tribe tech tree nodes; shared storage upgrades; emblem/banners; forum themes.
- **Map Structures:** Building/repairing beacons, wonders, signal fires; province locks; outpost construction (optional worlds).
- **Events:** Event shop exchanges (cosmetics, QoL vouchers), entry/participation fees for trials.
- **Cosmetics:** Village skins, unit skins, markers purchasable with tokens/currency; non-power.
- **Logistics:** Trade taxes, convoy fees, resource balancer fees; rush costs (capped time savers).
- **Maintenance/Decay:** Over-cap population penalties; wall repair; occupation taxes on newly captured villages.
- **Recovery:** Hospital/healer recovery consumes resources; rebuild after siege.
- **Soft Waste:** Storage overflow lost if capped; resource decay (optional hardcore) for hoarded amounts.

## Player Archetypes & Economy Interaction
- **Farmer/Builder:** Focus on production buildings, storage, tribe aid; trades surplus; needs protection and walls; invests in tribe projects.
- **Raider:** Heavy cav/light inf for plunder; relies on finding targets; uses resource balancer to dump loot; funds fast troop churn.
- **Trader:** Exploits market spreads; invests in Market/Storage; runs caravans; benefits from trade winds events; risk of interception on hardcore worlds.
- **Diplomat/Support Main:** Sends support, contributes resources to tribe; spends on defensive tech and storage; earns tokens via tribe quests.
- **Whale-Free Competitive:** Optimizes events/quests, efficient farming, balanced build; limited premium means using capped time savers and cosmetics only.

## Anti-Hoarding & Anti-Inflation Systems
- **Storage Caps:** Hard caps per resource until upgraded; overflow lost; vault protects small % from plunder.
- **Soft Decay:** Optional gradual decay on amounts above 80% of storage; disabled on casual worlds.
- **Dynamic Costs:** Late-game buildings/tech scale up; conquest costs rising for high-village empires.
- **Megaproject Sinks:** Wonders/beacons requiring massive, escalating deliveries; tribe-wide contributions.
- **Event Sinks:** Token shops that rotate; cosmetic bundles; reroll tokens; tribe tech funding.
- **Trade Taxes:** Prevent infinite loop trades; higher for imbalanced offers; dynamic taxes by shortage/surplus.
- **Population Pressure:** High pop drives food demand; over-cap penalties discourage mass hoarding of troops/resources without upkeep.
- **Diminishing Farm Returns:** Repeatedly raiding same barb/player yields reduced loot for a cooldown window.
- **Aid Caps:** Daily limits on sends/receives; distance-based losses; alt-pushing mitigations.

## Trading System
- **Market Mechanics:**
  - Create offers (give X for Y) with expiry; accept offers instantly if matching.
  - Fixed-rate trades allowed on beginner worlds; open rates on advanced worlds.
  - Surplus/shortage indicators; recommended fair ranges; anti-exploit floor/ceiling.
- **Caravans:**
  - Capacity based on Market level; speed affected by world/unit speed; taxed by distance; risk of interception on hardcore.
  - Return trips considered; UI shows ETA and net after tax.
- **Premium vs Non-Premium:** No pay-to-win. Premium can add QoL (extra offer slots, UI filters), but same caps and taxes.
- **Tribe-Internal Trade:** Reduced tax; priority lanes; aid requests board with caps; visibility/audit logs to deter pushing.
- **Bulk Tools:** Resource balancer across owned villages; fees to prevent free teleportation; respects storage caps.

## Risk vs Reward
- **Transporting:** Longer routes yield higher tax/risk; caravans can be intercepted (hardcore) or delayed; resource visibility invites raids.
- **Raiding:** Gains loot, but exposes troops to ambush/traps; attacking strong targets costly; attacking weak targets reduced by morale and protection rules.
- **Hoarding:** Large stockpiles risk plunder and decay; better to invest in upgrades or tribe projects.
- **Event Participation:** PvE camps drop resources/tokens but cost troops; high difficulty yields better returns; time-limited.

## Example Economic Flows
- **Early Game (Farmer):** Upgrade resource fields to 6–8; build Storage/Granary; small raids on barbs; resources → Wall/Barracks → Spears. Trades surplus clay for iron via market. Uses daily task rewards to smooth shortages.
- **Early Game (Raider):** Rushes Skirmisher Cav; farms barbs; balances with resource balancer; spends loot on Stable/Workshop + rams; keeps storage low to avoid plunder.
- **Mid Game (Balanced):** Multiple villages; uses trade routes to funnel to siege hub; invests in tribe tech; mints standards for first conquests; participates in events for tokens; uses vault to protect core stock.
- **Late Game (War Tribe):** Massive resource needs for wonder/beacons; coordinated deliveries; conquest costs rising; plunder fuels troop rebuilds; trade winds event shifts strategy to trading surplus wood for iron shortages.
- **Late Joiner (Seasonal World):** Receives catch-up production buff; completes quests for resource bundles; focuses on efficient builds; uses market to close gaps; avoids heavy raids until defenses up.

## Implementation TODOs
- [x] Resource config per world: production rates, storage/vault protection %, decay toggles, dynamic cost scalers, aid caps/taxes. _(config/config.php covers baseline knobs; add DB schema & world-level overrides)_
- [x] Resource sinks: implement minting (coins/seals/standards), tribe projects currency, megaproject deliveries (wonders/beacons), and occupation taxes. _(sink plan below)_
- [x] Anti-hoarding: overflow loss/decay above threshold (world-optional), diminishing plunder returns per attacker→target cooldown, rising conquest costs for large empires. _(anti-hoard spec below; decay implemented via RESOURCE_DECAY_* constants in ResourceManager)_
 - [x] Trade system: offer/accept APIs with tax by distance/power delta; fixed/open rate modes; aid board with caps and audit logs; balancer with fees and cap checks. _(trade system spec below)_
- [x] Event economy: token balances with expiry, event shops with caps, harvest/trade wind modifiers applied per world. _(event economy spec below)_
 - [x] Catch-up buffs: late-joiner production bonuses and protection; ensure non-stacking with beginner protection abuse; expiration rules. _(buff spec below)_
 - [x] Telemetry: metrics on production, sinks (minting, tribe projects), trade volumes, plunder/decay losses, and aid flows; alerts on anomalies. _(economy_metrics.log now records trade sends/offers/accepts with hashed IP/UA + totals; extend to other flows next)_
- [x] Safeguards: cap storage overflows, block trades/aid to protected alts (power delta + IP/alt flags), and enforce fair-market bounds on offers to reduce pushing. _(TradeManager enforces headroom, fair ratios on create/accept, and now blocks linked/flagged accounts or extreme power gaps with ERR_ALT_BLOCK reasons)_
- [x] Error codes: standardize economy errors (`ERR_CAP`, `ERR_TAX`, `ERR_ALT_BLOCK`, `ERR_RATE_LIMIT`) and surface retry/next steps in UI. _(see error code spec below)_
- [x] Auditing: append-only logs for trades/aid/minting with actor, target, amounts, ip_hash/ua_hash, and world_id; retain 180 days. _(trade/a id logger added; writes hashed IP/UA + payload to logs/trade_audit.log)_
- [x] Load shedding: if trade/aid endpoints face spikes, degrade gracefully (queue/try-later) instead of overloading DB; emit backpressure metric. _(trade manager now polls active routes/open offers with a soft limit + backpressure metric `trade_load_shed` and returns ERR_RATE_LIMIT with retry window)_
- [x] Validation: block zero/negative resource sends, enforce storage limits at send/receive, and reject offers with extreme exchange ratios outside configured band. _(trade send now checks target storage headroom + ERR_RATIO already enforced on offers)_
- [ ] Economy tests: unit tests for vault protection math, tax calculation, overflow/decay triggers, and fair-market bounds; integration tests for trade/aid flows with caps and power-delta taxes applied. _(baseline decay + fair-ratio/merchant caps covered in `tests/economy_test.php`; add vault/tax/aid cases next)_
- [ ] Pricing guardrails: define per-archetype min/max dynamic cost scalers, event modifier bounds, and conquest cost scaling curves to prevent runaway inflation or too-cheap conquest on late worlds. Document defaults and enforcement points in config loader.

### Pricing Guardrails — Implementation Notes
- Dynamic cost scalers (per archetype): casual 0.9–1.1, classic 1.0–1.2, hardcore 1.0–1.3. Clamp world overrides at load; log clamp with world id and setting. Apply to buildings/tech/minting calculators.
- Event modifier bounds: `EVENT_PROD_MAX=1.5`, `EVENT_TAX_MIN=0.5`; clamp and log when configs exceed; admin UI shows active modifiers + bounds.
- Conquest cost scaling: empire surcharge starts at `VILLAGE_THRESHOLD=20`, +2% per 5 villages, capped +30% (hard cap +50%). Applies to minting and conquest-unit training. Reports show “Empire surcharge +X%”.
- Enforcement points: config loader clamps and emits warning; WorldManager exposes `guardrail_status` for admin; reports/tooltips include applied scaler/modifier/surcharge when non-1.0.
- Monitoring: metric `economy_guardrail_clamps_total` labelled by type (cost_scaler|event_mod|conquest_scale) and world; alert on sustained clamp rates indicating misconfig.

### Economy Error Codes (Standard)
- `ERR_CAP`: storage/aid cap hit. Payload: `{ "retry_after_sec": 0, "cap_type": "send|receive|storage" }`.
- `ERR_TAX`: trade/aid would exceed max tax or violates floor/ceiling; include `{ "effective_tax_pct": number, "max_tax_pct": number }`.
- `ERR_ALT_BLOCK`: blocked by alt/power-delta/IP link check or protected target; include `{ "reason": "protected|power_delta|alt_link" }`.
- `ERR_RATE_LIMIT`: per-player/per-target rate cap exceeded; include `{ "retry_after_sec": number }`.
- `ERR_RATIO`: offer exchange ratio outside allowed band; include `{ "offered_ratio": number, "min_ratio": number, "max_ratio": number }`.
- `ERR_VALIDATION`: zero/negative amounts or missing resources; include `{ "field": "wood|clay|iron|tokens", "message": "positive_required|insufficient" }`.
- **UI surfacing:** show friendly message, highlight offending fields, and display retry/next steps (e.g., wait N seconds, reduce amount, adjust ratio). Errors returned as JSON with `code` + `details` and HTTP 400/429 as appropriate.

## Progress
- Added per-world economy knobs on `worlds` (`resource_multiplier`, `vault_protect_pct`) with migration/backfill defaults; `ResourceManager` applies per-world production scaling.
- Vault protection percent now applied in plunder: BattleManager subtracts the greater of hiding place protection or world vault % per resource before loot.
- Resource production multiplier now sourced via WorldManager in ResourceManager to align production rates with per-world config.
- Resource tick now loads per-world economy config up front so decay/threshold toggles apply consistently and no undefined config paths fire during updates.
- Trade/aid errors use standardized economy codes; send flow enforces storage headroom and resource availability with `ERR_RES` instead of `ERR_CAP`.
- Anti-push/alt guard: TradeManager now blocks trades to linked/flagged accounts and extreme power gaps for protected/low-point players, returning `ERR_ALT_BLOCK` (`alt_link`/`alt_flag`/`power_delta`).
- Trade load-shedding path returns `ERR_RATE_LIMIT` when active routes/offers cross soft caps and logs `trade_load_shed` metrics with counts/limits for tuning.

## Acceptance Criteria
- World configs apply correct production/storage/vault/decay values and caps for the selected archetype; overrides logged.
- Minting/tribe projects/megastructure deliveries consume resources and respect daily caps; errors surfaced with reason codes.
- Trade/aid routes enforce taxes/caps and block power-delta exploits; audit logs capture sender/receiver, amounts, tax, and reason codes on blocks.
- Diminishing plunder and aid caps reset on schedule; repeated farm attempts show reduced loot; tests cover abuse edge cases.
- Event tokens expire correctly; event shop caps enforced; late-joiner buffs applied once and expire on schedule.
- Safeguards and error codes (`ERR_CAP`, `ERR_TAX`, `ERR_ALT_BLOCK`, `ERR_RATE_LIMIT`, `ERR_RATIO`, `ERR_VALIDATION`) enforced and surfaced with guidance in UI.
- Auditing retains append-only logs (trades/aid/minting) for 180 days with actor/target/ip_hash/ua_hash/world_id.
- Load shedding on trade/aid spikes returns queue/try-later with backpressure metrics; no DB timeouts.
- Validation rejects zero/negative sends, enforces storage limits at send/receive, and blocks extreme exchange ratios; tests cover these cases.
- Vault math validated: loot calculations use max(vault_pct, hiding_place) per resource; reports show protected amount and applied percentage correctly.
- Event tokens expire on schedule; shop caps enforced per player/global; purchases reject with clear errors when expired/capped; event modifiers apply and surface in UI.
- Catch-up buffs apply once per eligible player, do not stack with beginner protection, auto-expire, and rebuild packs are rate-limited; abuse blocked by thresholds.
- Economy tests cover vault math, tax, decay/DR triggers, trade/aid caps, fair-market bounds, power-delta taxes, and event expiry/shop caps in integration scenarios.

### Catch-Up Buffs Spec
- **Eligibility:** Players whose account age or points are below configured thresholds relative to world age (e.g., joined >7 days after world start or below 50% of median points).
- **Buffs:** +X% production (time-limited, e.g., 72h), reduced build/recruit times by Y%, and temporary protection against plunder/aid abuse caps adjusted. Buffs do not stack with beginner protection; if beginner is active, catch-up waits or applies reduced effect.
- **Delivery:** Granted on login or world join; visible banner in UI with remaining duration; one-time per season/world. Rebuild packs (small resource bundles) optionally granted on wipe events, capped per day.
- **Anti-abuse:** Disable buffs when player exceeds point threshold or after duration ends; strip on PvP attack initiation if world rules require. Log activations and expiries; rate-limit rebuild pack requests and tie to recent losses.
- **Config:** Per-world settings for production %, time %, duration, point thresholds, and rebuild pack contents; admin audit when changed.
- **Telemetry:** Emit buff grants, expiries, and abuse blocks; monitor adoption and churn deltas for tuning.

### Event Economy Spec
- **Currencies:** Event tokens tracked per world; have `granted_at`, `expires_at`, and `source` fields. Expired tokens auto-removed by daily job; UI shows countdown.
- **Shops:** Event shop configs per world with item caps, costs, and availability windows. Enforce per-item and per-account caps; items are cosmetic/QoL only (no power boosts).
- **Harvest/Trade Winds Modifiers:** World flags to enable event modifiers (e.g., +20% production for 48h, -15% trade tax). Modifiers carry start/end timestamps; applied in ResourceManager/Market and surfaced in UI.
- **Grants:** Tokens granted via events/quests/battles; grant endpoint validates caps and sets expiry. Logs include actor, amount, source, world_id.
- **Redemption:** Purchase endpoint checks token balance, caps, and window; deducts tokens atomically. Returns `ERR_EVENT_EXPIRED`/`ERR_CAP`/`ERR_TOKENS` as needed.
- **Telemetry/Audit:** Metrics for tokens granted/spent/expired, shop purchases by item, and modifier activation; append-only logs for grants/purchases with ip_hash/ua_hash.
- **Abuse Guards:** Block event-token trades between accounts; cap token earn per day; detect and throttle repeated farm loops; expire unspent tokens cleanly on event end with clear messaging.
- **UI/UX:** Event banner shows timers, current modifiers, token balance with expiry, and shop caps; purchase flow surfaces remaining caps and errors clearly.

### Trade System Spec
- **Modes:** `fixed_rate` (beginner/casual worlds with configured fair ratios and tight bands) and `open_rate` (free market). World flag controls mode; UI shows mode badge.
- **Offers API:** Create offer with give/get amounts and expiry; validate storage, caravans, and fair-market bounds (ratio band in open mode). Return `ERR_RATIO`/`ERR_RES`/`ERR_CAP` on failure.
- **Accept API:** Match compatible offers; apply tax = distance factor × power-delta factor with floor/ceiling; compute arrival times using world speed. Reject if storage at target full (`ERR_CAP`) or offer expired.
- **Aid Board:** Separate endpoint for tribe aid with daily send/receive caps, distance tax, and audit logging. Power-delta guard blocks aid to alts/low-power abuse (`ERR_ALT_BLOCK`).
- **Resource Balancer:** Server-side balancer to redistribute across owned villages; applies transfer fee; respects storage caps and caps on instant “teleport.” Returns `ERR_CAP`/`ERR_RES`/`ERR_FEE`.
- **Caravans:** Capacity = base per Market level; speed from world/unit speed; taxed by distance and mode; return trip time included. Aid/offer payload includes ETA and net-after-tax for UI.
- **Auditing/Telemetry:** Log all create/accept/aid/balancer actions with actor/target, amounts, tax, ip_hash/ua_hash/world_id; emit metrics for volume, tax collected, cap hits, and rate-limit hits.

### Anti-Hoarding & Anti-Inflation Spec
- **Overflow/Decay:** Optional per-world `RESOURCE_DECAY_ENABLED` with `DECAY_THRESHOLD_PCT` (default 80%) and `DECAY_RATE_PER_HOUR` (e.g., 1–3% of amount above threshold). Applied in resource tick; decay logged to telemetry; disabled on casual worlds.
- **Overflow Loss:** Hard cap enforced at storage; incoming production/loot beyond cap is dropped; report/tooltip shows “Overflow loss: X”.
- **Diminishing Plunder:** Per (attacker, target) cooldown window (e.g., 2h). Loot multiplier starts at 1.0, steps down (0.75, 0.5, 0.25) on successive raids within window; resets after cooldown. Separate bands for barb vs player targets (so farming barbs hits DR sooner).
- **Empire Scaling Costs:** For players above `EMPIRE_VILLAGE_THRESHOLD` (e.g., 20 villages), apply incremental cost multiplier to minting/conquest costs and building upgrades in new villages (e.g., +2–5% per 5 villages, capped). Configured per world; surfaced in UI as “Empire upkeep.”
- **Fairness/Transparency:** All penalties surfaced in UI (decay warnings at 75%+ storage, DR warning when hitting same target repeatedly). Battle/loot reports show applied DR multiplier.
- **Telemetry:** Emit metrics for decay amounts, DR hits, empire surcharge applied; alert on spikes indicating mis-tuning.

## Open Questions
- Should soft decay on storage overflow be global or world-optional, and what decay rate feels fair (e.g., 1%/h above 80%)?
- How aggressive should diminishing plunder be on repeated barb farming vs players to avoid punishing legit skirmishes?
- Do aid taxes scale by distance only, power delta only, or both? Need formula defaults for UI explanation.
- Are event tokens transferable between worlds or strictly per-world? Clarify to prevent hoarding/exploit.
- What thresholds trigger economy alerts (decay/DR hits, trade/aid rate-limit spikes), and who owns remediation per world?

## Profiling & Load Plan
- Economy tick soak tests with decay/DR/empire surcharges enabled at scale; measure p50/p95 latency and DB load.
- Market/aid stress: high-volume trade/aid requests with taxes/caps/audit logging; ensure rate limits and backpressure prevent DB timeouts.
- Minting/tribe project/megastructure load: concurrent contributions to cap enforcement; verify no lock contention and correct surcharge application.
- Event economy soak: token grants, expiries, and shop purchases at volume; validate caps and expiry behavior and payload sizes.

## QA & Tests
- Unit tests: vault/hiding-place math, tax calc, decay/DR triggers, empire surcharge, fair-market bounds, and error codes.
- Integration: trade/aid flows with caps, power-delta taxes, rate limits, and load-shedding paths; ensure auditing records entries.
- Event economy: token expiry, shop caps, `ERR_EVENT_EXPIRED`/`ERR_EVENT_CAP`/`ERR_TOKENS`, and modifier application surfaced in UI.
- Catch-up buffs: eligibility, single-application, non-stacking with beginner protection, expiry/removal, and rebuild pack rate limits.
- Telemetry: emit metrics for production/sinks/trade/aid/decay/DR; alert thresholds verified; log retention/audit entries confirmed.
- Pricing guardrails: test min/max dynamic cost scalers per archetype, event modifier bounds, and conquest cost scaling curves; ensure costs clamp to bounds, alerts fire on violations, and reports/logs show applied scalers.

## Rollout Checklist
- [x] Feature flags for decay and plunder DR per world; defaults remain legacy-friendly via constants. _(WorldManager exposes `resource_decay_*` and new `plunder_dr_enabled`; other flags like empire surcharge/trade/event expiry still TODO)_
- [ ] Schema migrations for economy caps/logs tested with rollback; ensure indexes for high-churn tables (trade/aid logs).
- [ ] Backward-compatible API responses for trade/aid/minting while new caps/fields propagate; include versioning.
- [ ] Release comms: explain decay/DR/taxes and fair-play safeguards; UI tooltips updated with formulas/examples.
- [ ] Monitoring: dashboards for economy metrics (production/sinks, trade/aid volume, cap hits, decay/DR events); alerts on anomalies with runbooks/owners.
- [ ] Guardrail logging: emit metrics/logs when dynamic cost scalers/event modifiers hit min/max bounds or conquest cost scaling applies; surface in admin dashboards to catch misconfigurations.
- [ ] QA passes: economy unit/integration tests run; trade/aid load tests with rate limits/load shedding; event token expiry/shop caps validated before rollout.

## Monitoring Plan
- Track economy tick latency, decay/DR application counts, and empire surcharge hits; alert on spikes or missed applications.
- Monitor trade/aid/minting error rates and cap hits; alert on surges indicating misconfigurations or abuse.
- Watch event token expiries and shop purchase caps; alert if expiries fail or caps are bypassed.
- Track pricing guardrail clamps (min/max scalers, conquest cost scaling, event modifier bounds); alert if clamp rates spike or stay at bound, suggesting mis-tuned configs.
- Dashboard for trade/aid volumes, average payload sizes, and rate-limit hits to catch regressions.

### Resource Sink Plan
- **Minting:** Coins/Seals/Standards crafted in Hall/Academy with rising costs; daily mint cap per account; consumes wood/clay/iron and optional token sink. Required for Standard Bearers.
- **Tribe Projects:** Tribe tech nodes and shared storage consume member-delivered resources/tribe tokens; capped contributions per day to prevent push abuse; progress stored per world.
- **Megaprojects:** Wonders/Beacons require staged deliveries with escalating batches; enforce per-player daily contribution caps and distance-based aid tax if delivering via aid.
- **Occupation Taxes:** Recently captured villages apply production debuff and tax (resource siphon) until allegiance recovers; tax scales with world settings and time since capture.
- **Event Sinks:** Rotating event shops with expiry; purchase limits per item; no permanent stat boosts; cosmetic/QoL only to avoid pay-to-win.
