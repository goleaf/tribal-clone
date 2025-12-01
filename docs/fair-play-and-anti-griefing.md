# Fair Play & Anti-Griefing Systems — Medieval Tribal War Browser MMO

## Design Goals
- Reduce newbie farming and repetitive harassment of low-power players.
- Prevent coordinated bullying (multi-account focus firing, denial of play) and resource pushing between alts or dominant tribes to smurf.
- Maintain competitive integrity while preserving skill expression and legitimate aggression.
- Encourage meaningful wars over fair targets instead of predation of the weakest.
- Create recovery paths so setbacks do not cause immediate churn.

## Protection for New/Weak Players
- **Beginner’s Shield:** Time-based (e.g., first 72h) and/or points-based until player reaches power threshold or initiates an offensive action.
- **Graduated Damage Reduction:** Incoming damage/plunder reduced based on power delta; floor to avoid invulnerability (e.g., 70% reduction vs attackers 5x size, scaling down to 0 at 1.5x).
- **Attack Limits:** Daily cap on attacks received from the same attacker or tribe against players under threshold (e.g., 5 attacks/day per attacker; 20/day per tribe).
- **Diminishing Plunder:** Each subsequent successful raid within a short window yields less loot (e.g., -30% loot per hit stacking to -90%). Resets after cooldown.
- **Wall-to-Resource Protection Coupling:** Higher wall levels increase protected resources for low-power players to discourage farm loops.
- **Safe Window After Devastation:** Post-wipe shield (e.g., 8–12h) triggers when defenses drop below defined troop/resource threshold; blocked by repeat offense from same attacker.
- **Opt-in “Truce Token” (limited):** Low-power players can activate a short truce that forbids outgoing attacks; limited uses per season to avoid abuse.
- **Spawn Relocation:** Limited relocations within starter sectors during protection; blocks relocation if hostile commands are en route.
- **Anti-Clean Sweep:** After a successful capture of a weak player’s village, remaining villages get temporary boosted wall effectiveness/production to deter chain-takes.

## Morale or Balancing Systems
- **Classic Morale:** Attacker strength scaled down based on attacker vs defender points ratio. Pros: simple, proven; Cons: can feel punitive to high-skill attackers.
- **Frontline Fatigue:** Long-distance attacks suffer combat effectiveness decay by distance/time. Pros: rewards local fights; Cons: may slow map-wide wars.
- **Resolve Buff:** Defenders gain % defense after successive defenses within a window, decaying over time. Pros: rewards active defenders; Cons: can prolong stalemates.
- **Strength-of-Number Dampening:** Multiple simultaneous attacks from same tribe against a small target incur stacking attack debuffs. Pros: discourages overkill; Cons: coordination edge reduced.
- **If Morale Disabled:** Use stronger protection windows, stricter attack caps vs low-power targets, and scaling plunder reduction to retain fairness without stat scaling.

## Support & Resource Transfer Rules
- **Aid Caps by Power Ratio:** Resource sends to players below X% of sender’s points limited per day; hard caps on gift size.
- **Diminishing Returns on Repeated Aid:** Each additional aid packet within 24h to the same recipient loses % effectiveness (taxed away or lost in transit).
- **Tribe-Only Military Aid:** Support troops allowed only within tribe or allied state; neutral aid forbidden to limit shell accounts.
- **Support Upkeep Ownership:** Supporting troops consume upkeep from their owner, not recipient, to prevent infinite stacking on small accounts.
- **Return Timer:** Minimum station time before recalling support to avoid “flash” support exploit.
- **Trade Tax by Distance:** Resource shipments taxed by distance and power delta to reduce pushing; higher tax when sending from strong to weak outside tribe.
- **Transfer Audit Flags:** Excessive aid between two accounts triggers cooldowns and review (design-level: impose soft caps and auto-cooldowns, not just enforcement).

## Surrender & Exit Options
- **Graceful Surrender:** Player can trigger a “white flag” preventing new incoming attacks for a short duration while auto-evacuating resources; disables outgoing attacks and conquest during that time.
- **Relocation After Crush:** If a player loses majority villages within short period, offer relocation to quieter sector with protection and partial rebuild packs (no troops).
- **Vassal/Protectorate Mode:** Voluntary state under a stronger tribe with tribute/resource sharing and attack immunity from protector tribe; clear exit timer to avoid abuse.
- **Retire with Honors:** Convert account to spectator/advisor for that world, keeping chat rights and cosmetic badges; frees villages to barbarian status slowly.
- **Re-entry Boost:** Returning lapsed players get catch-up production boosts and build time reductions for limited duration; blocked from PvP exploits by attack throttles during boost.

## Tribe-Level Fairness
- **Tribe Size Caps:** Fixed member limit; prevents mega-alliances under one tag.
- **Alliance Limits:** Cap number of formal alliances/NAPs; force choices and reduce map-wide peace webs.
- **Coalition Penalties:** If multiple tribes coordinate against one significantly weaker tribe, shared attack debuff or reduced plunder after N simultaneous wars detected by size ratios.
- **Diplomacy Cooldowns:** Changing states (ally↔war↔NAP) imposes cooldowns to prevent exploitative flip-flopping for safe passage.
- **Shared Victory Conditions:** Prevent shell tribes from feeding main tribe by requiring minimum contribution per tribe member to qualify for endgame credit.
- **Spy Policy:** Tribe-hopping cooldowns and delayed intel sharing for new members to curb espionage abuse.

## Examples (Abuse Prevention in Action)
- **Newbie Farm Attempt:** High-power player raids a newcomer 6 times in an hour. Diminishing plunder drops loot to 10%, damage reduction lowers casualties on defender, and daily attack cap blocks further hits; attacker shifts to fairer targets.
- **Resource Pushing:** Main account tries to funnel large clay shipments to an alt. Aid caps and distance tax remove most of the value; repeated sends trigger a cooldown, making pushing inefficient.
- **Coalition Dogpile:** Two top tribes declare on a small tribe. Coalition penalty reduces their plunder/attack effectiveness against targets below threshold, encouraging them to fight peers instead.
- **Support Abuse:** Player stacks 10 allies’ armies in one village. Upkeep remains with senders and return timers prevent flash dumps; strength-of-number dampening in morale system reduces overstack advantage.
- **Comeback Path:** Player loses 3 villages in 12 hours; auto-triggered safe window and relocation offer let them move to a calmer sector with production boosts, retaining engagement rather than quitting.

## Implementation TODOs
- [ ] Build a “protection state” service that calculates shields, attack caps, and plunder modifiers per target based on point ratios, recent hits, and protection flags; expose to combat resolver.
- [x] Add server-side caps: per-attacker→defender daily cap enforced at send time (BattleManager); tribe-level cap still pending.
- [ ] Implement diminishing loot stack per attacker→defender keyed on success timestamp; reset after cooldown; log final loot percent in battle report.
- [ ] Add devastation shield trigger: when defender drops below troop/resource threshold, apply temporary shield unless attacker is same as last shield trigger.
- [ ] Resource/aid tax: distance + power-delta based tax; enforce hard caps per sender/recipient per day; emit audit events for outliers.
- [ ] Coalition detection: if two or more tribes above size threshold declare/attack a small tribe, apply configurable attack/plunder debuff and flag for admin review.
- [ ] Relocation offer flow: detect heavy loss streak, present relocation UI, block if hostile commands en route; include cooldown and one-time limit per season.
- [ ] Anti-farm wall coupling: increase hidden resource protection and plunder decay when low-power defenders keep wall above threshold; surface benefit in UI to encourage upkeep.
- [ ] Attack intent classification: tag commands as raid/siege/conquest/fake; apply stricter caps to repeated low-payload raids and zero-siege fakes; log intent in fairness metrics.
- [ ] Tribe-level fairness hooks: debuff stacked incoming from allied tribes beyond N simultaneous wars; block treaty flip-flops within 24h of active war to prevent safe-passage exploits.
- [ ] Fair target finder: optional UI that highlights similar-strength targets and shows “fair fight” bonus XP/loot to nudge aggression away from rookies.
- [ ] World config matrix: per-world tuning for caps, shield durations, plunder decay, and coalition thresholds; export/import presets for speedy launches.
- [ ] Appeals flow: in-game “appeal fairness flag” button on battle reports/blocked screens; routes to support queue with context (reason code, attacker/defender IDs, timestamps).
- [ ] GM overrides: admin tool to lift specific caps for events or fix false positives; all overrides logged with actor/reason and auto-expire timers.

## Observability & Enforcement
- [ ] Metrics: emit counters for blocked attacks (reason code), shields activated, plunder reductions applied, aid caps hit, relocation offers accepted/declined.
- [ ] Dashboards: fairness overview with attack-cap hits over time, top offenders, protected players by bracket, coalition debuff activations.
- [ ] Alerts: page on spikes of `ATTACK_CAP_HIT` or >5 coalition debuffs/hour; alert on relocation accept rate drops (broken flow).
- [ ] Reports: battle reports include applied fairness modifiers (damage/plunder reduction %, attack debuff flags) and whether attack counted toward daily caps.
- [ ] Admin tools: searchable log by attacker/defender/tribe with timestamps and reason codes; ability to adjust thresholds per world with audit trail.

## QA & Rollout
- [ ] Simulation tests: scripted scenarios for newbie farm, coalition dogpile, aid pushing, and relocation trigger; assert caps and modifiers fire with correct reason codes.
- [ ] Load tests: attack-cap enforcement under high-volume fake spam to ensure no performance regressions.
- [ ] A/B world flags: stage new fairness knobs on test world first; compare churn/engagement and attack distribution before enabling globally.
- [ ] Player comms: patch notes and in-game mail explaining protections, reason codes, and how to appeal; link to support form for mistaken caps.

## Data & Retention
- [ ] Store fairness logs (blocked actions, modifiers applied, appeals) for 30 days hot, then archive to cold storage for 6 months for dispute resolution.
- [ ] PII minimization: hash IP/UA in fairness logs; store IDs and timestamps only; ensure export for GDPR/CCPA requests includes fairness flags.
- [ ] Cleanup job: nightly purge expired fairness entries and expired GM overrides; emit metrics on rows deleted and errors.

## Open Questions
- Should shields pause loyalty decay or just combat damage? Define to avoid conquest exploits.
- Do coalition debuffs apply to scouting/fakes or only damage/plunder? Clarify to prevent loopholes.
- What is the exact reset timer for diminishing plunder and attack caps (per attacker vs per tribe)?
- How to handle shared IP households to avoid unfair linking/flags? Need policy + detection nuance.
- [ ] Analytics: track before/after deltas in attacks vs low-power players, average plunder per attack by bracket, relocation acceptance rate, and repeat-offender counts.
- [ ] Rollback plan: feature flags per protection component (caps, plunder decay, coalition debuff) with safe defaults; documented rollback steps and owners.
