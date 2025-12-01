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
- [x] Anti-hoarding: overflow loss/decay above threshold (world-optional), diminishing plunder returns per attacker→target cooldown, rising conquest costs for large empires. _(anti-hoard spec below)_
- [ ] Trade system: offer/accept APIs with tax by distance/power delta; fixed/open rate modes; aid board with caps and audit logs; balancer with fees and cap checks.
- [ ] Event economy: token balances with expiry, event shops with caps, harvest/trade wind modifiers applied per world.
- [ ] Catch-up buffs: late-joiner production bonuses and protection; ensure non-stacking with beginner protection abuse; expiration rules.
- [ ] Telemetry: metrics on production, sinks (minting, tribe projects), trade volumes, plunder/decay losses, and aid flows; alerts on anomalies.
- [ ] Safeguards: cap storage overflows, block trades/aid to protected alts (power delta + IP/alt flags), and enforce fair-market bounds on offers to reduce pushing.
- [x] Error codes: standardize economy errors (`ERR_CAP`, `ERR_TAX`, `ERR_ALT_BLOCK`, `ERR_RATE_LIMIT`) and surface retry/next steps in UI. _(see error code spec below)_
- [ ] Auditing: append-only logs for trades/aid/minting with actor, target, amounts, ip_hash/ua_hash, and world_id; retain 180 days.
- [ ] Load shedding: if trade/aid endpoints face spikes, degrade gracefully (queue/try-later) instead of overloading DB; emit backpressure metric.
- [ ] Validation: block zero/negative resource sends, enforce storage limits at send/receive, and reject offers with extreme exchange ratios outside configured band.
- [ ] Economy tests: unit tests for vault protection math, tax calculation, overflow/decay triggers, and fair-market bounds; integration tests for trade/aid flows with caps and power-delta taxes applied.

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

## Acceptance Criteria
- World configs apply correct production/storage/vault/decay values and caps for the selected archetype; overrides logged.
- Minting/tribe projects/megastructure deliveries consume resources and respect daily caps; errors surfaced with reason codes.
- Trade/aid routes enforce taxes/caps and block power-delta exploits; audit logs capture sender/receiver, amounts, tax, and reason codes on blocks.
- Diminishing plunder and aid caps reset on schedule; repeated farm attempts show reduced loot; tests cover abuse edge cases.
- Event tokens expire correctly; event shop caps enforced; late-joiner buffs applied once and expire on schedule.

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

### Resource Sink Plan
- **Minting:** Coins/Seals/Standards crafted in Hall/Academy with rising costs; daily mint cap per account; consumes wood/clay/iron and optional token sink. Required for Standard Bearers.
- **Tribe Projects:** Tribe tech nodes and shared storage consume member-delivered resources/tribe tokens; capped contributions per day to prevent push abuse; progress stored per world.
- **Megaprojects:** Wonders/Beacons require staged deliveries with escalating batches; enforce per-player daily contribution caps and distance-based aid tax if delivering via aid.
- **Occupation Taxes:** Recently captured villages apply production debuff and tax (resource siphon) until allegiance recovers; tax scales with world settings and time since capture.
- **Event Sinks:** Rotating event shops with expiry; purchase limits per item; no permanent stat boosts; cosmetic/QoL only to avoid pay-to-win.
