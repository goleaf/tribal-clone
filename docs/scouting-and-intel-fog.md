# Scouting, Fog of War & Intelligence Systems — Medieval Tribal War Browser MMO

## Fog of War Concept
- **Default Visibility:**
  - Own villages: full info (troops, queues, resources, building levels, commands).
  - Tribe/ally (if state allows): shared vision of villages/markers/commands (configurable per world).
  - Neutral/enemy: see village name, coordinates, owner/tribe tag, point score, diplomacy state, approximate wall silhouette; no troop/resource info.
  - Barbarian/POI: visible location and type; strength/resources unknown without scouting (varies by world).
- **Map Layers:**
  - Fog covers tiles beyond a radius around owned/tribe villages and recent scout paths; further tiles show only terrain/POI hints.
  - Intel overlays (watchtower, relic signal, beacon) can temporarily pierce fog in a radius.
- **Command Visibility:**
  - Incoming commands to your villages always visible.
  - Outgoing commands visible to owner/tribe (if shared intel enabled).
  - Enemy movements hidden unless revealed by scouts, watchtowers, or special objectives.

## Scout/Spy Units (Roles, Risks, Uses)
- **Pathfinder (Fast Scout):** Cheap, fast; good for surface intel (troop presence, resources). Fragile; dies easily to counter-scouts.
- **Deep Spy:** Slower, higher stealth; can reveal building levels, queues, and garrison composition. Costly; higher pop.
- **Counter-Scout (Sentry):** Defensive scout variant stationed at home; boosts defense vs scouts; small vision radius bonus.
- **Shadow Rider (Cavalry Scout):** Fast long-range; reveals movement hints (recent commands) at lower fidelity.
- **Saboteur (if enabled):** Chance to disable traps or reduce wall effectiveness temporarily; high risk of capture.
- **Envoy/Agent (intel courier):** Can place temporary markers behind lines; low combat value.
- **Watchtower Crew (building-based):** Not a unit but a garrison; increases detection radius and provides early warnings.

## Intel Types (What Can Be Learned)
- Troop counts by type; ranges or exact depending on success.
- Building levels (overall or targeted: Wall, Watchtower, Academy, Barracks, Storage).
- Resource stockpiles (approximate amounts, vault protection noted).
- Population used/free and farm level (if revealed by advanced scouts).
- Queue information: active build/recruit/research queues and remaining time (advanced only).
- Online activity hints: “recent activity within X minutes” (optional world rule; never exact online status).
- Tribe relations/diplomacy state of target; recent tribe change flag.
- Recent battles at target: last battle time and outcome summary.
- Unit movement hints: commands recently left/arrived; direction and type (attack/support/trade) without numbers.
- Trap/watchtower presence and level bands.
- Loyalty/allegiance value (if noble system); visible only on high-fidelity reports.
- Relic/artifact possession (if stored in village).

## Scouting Outcomes (States & Example Reports)
- **Successful (Full Fidelity):** All targeted intel revealed with exact numbers/levels; scouts mostly survive.
  - Example: `Full Recon — Bramblehold (498|501): Troops: 820 Spears, 260 Swords, 140 Archers, 40 Rams, 0 Nobles. Wall 10, Watchtower 6, Academy 1. Resources: W12,340 C11,220 I9,880 (Vault hides 2k each). Loyalty 87. No traps detected.`
- **Partially Successful (Limited Fidelity):** Scouts survive with losses; intel shown in ranges/bands.
  - Example: `Partial Recon — Oakridge (512|489): Troops estimated 500–700 Spears, 150–250 Swords. Wall ~9–11. Resources: Moderate (~8–12k each). Traps suspected. 6/20 scouts lost.`
- **Failed (No Intel):** All scouts die before reporting; attacker learns nothing except failure.
  - Example: `Recon Failed — Elmwatch (505|497): All scouts eliminated. No intel gathered. Defender likely has strong counter-scout presence.`
- **Trapped (Punished):** Traps trigger; scouts die; defender may gain origin info.
  - Example: `Recon Disarmed & Lost — Frosthill (500|500): Traps destroyed your scouts. Defender learned your origin (RavenTribe / Arlen).`
- **Ambushed (Counter-Offense):** Defender counter-attacks surviving scouts or captures them; delayed/false intel returned.
  - Example: `Ambush! — Your scouts were intercepted by counter-scouts. Limited intel: Wall high, troops unknown. 1/20 returned; report may be compromised.`

## Counter-Scouting Mechanics
- **Stationed Scouts & Sentries:** Defending scouts duel attackers; more sentries improve chances. Tribe techs can add flat defense.
- **Watchtower/Beacon Spire:** Extends detection radius; gives early warning timer; adds scout defense modifier. Can tag approaching scouts as “unknown command” with ETA.
- **Traps/Snares:** One-time or regenerating charges that kill/capture scouts before duel. Higher-level traps reveal attacker origin if triggered.
- **Decoy Information:**
  - Decoy garrisons/resources visible only to low-fidelity scouts.
  - Misdirection toggle (cooldown) forces banded/rounded results for attackers.
- **Signal Jammers (Tech):** Reduce report fidelity; convert exact values to ranges; hide queues.
- **Anti-Survey Mode:** Temporarily masks building upgrades/queues; consumes resources or has cooldown.
- **Interrogation (Tech/Building):** Chance to extract origin coords/tribe from captured scouts; logs for tribe.
- **False Follow-Up:** If enabled, defender can send a forged follow-up report to attacker (flagged with risk notice in logs); limited uses, cooldowns to prevent abuse.

## Intel Decay (Stale Information)
- **Aging Tiers:** Fresh (0–2h), Recent (2–12h), Old (12–48h), Stale (48h+). UI colors and tooltips indicate tier.
- **Automatic Degradation:** Exact numbers become ranges; building levels become bands; movement intel removed on Old+.
- **Overlay Fading:** Map markers fade opacity with age; hover/tap shows “Last scouted 18h ago.”
- **Refresh Prompts:** Ops planner flags key targets with stale intel; quick button to resend scouts.
- **Auto-Archive:** Reports older than N days move to archive; starred reports persist but marked stale.
- **Activity-Based Soft Refresh:** If a battle/trade report exists for the target within Y hours, some intel (troop presence) upgrades one tier fresher.

## Tribe Intel Sharing (Coordination)
- **Shared Report Bank:** Tribe-level inbox with tags (Frontline, Farm, Relic, OP). Permissions by role (Intel Officer can retag/star; recruits view-only).
- **Map Markers with Intel:** Pins carrying last-known troops/buildings/resources + timestamp. Color-coded by priority and operation; expire/fade with decay.
- **Intel Channels:** Dedicated chat/forum threads for intel dumps; supports embedded coords and report previews; role-gated posting to avoid spam.
- **Spy/Observer Ranks:** Limited-visibility roles for new members; full intel unlocked after probation to reduce leaks.
- **Sector Assignments:** Coverage map showing which sectors are fresh/stale; scouts assigned; auto-reminders to refresh.
- **Sharing Controls:** Redact resources or exact counts when sharing to allies; internal tribe sees full fidelity.
- **Audit Trails:** Logs of who shared/edited intel, marker placement, and report deletions.

## Advanced Features & Anti-Abuse
- **False Reports:**
  - Crafted by defenders after successful counter-scout to send misleading intel. Costs resources/tokens; limited per day; flagged internally as “possible misdirection” so attackers know risk.
  - Constraints prevent showing impossible states (e.g., zero troops + high resources simultaneously).
- **Fog-of-War Events:** Periodic weather/arcane storms reducing scout fidelity, speeding intel decay, or hiding movement lines. Countered by holding certain POIs or activating temporary boosts.
- **Info-Reveal Objectives:** Signal fires, relic obelisks, watch posts that when held reveal:
  - Movement pings in adjacent tiles.
  - Snapshot of building levels every N minutes.
  - Alert for noble-bearing commands within radius (without counts).
- **Interception & Path Watch:** High watchtower levels mark commonly used lanes; repeated use by same attacker increases chance to reveal origin/time of future commands.
- **Anti-Spam Controls:** Caps on scout commands per minute; diminishing returns (higher losses/lower fidelity) for repeated failed scouts on same target; attack/ally state warnings to avoid friendly-fire intel.
- **Premium Boundaries:** No pay-to-win intel boosts; monetization limited to QoL (report storage, UI skins). Any power-affecting intel modifiers tied to gameplay/world rules only.

## Example Reports (Varied Outcomes)
- **Full Success (Deep Spy):** `Deep Spy Report — Iron Nest (410|422): Troops: 600 Spears, 400 Swords, 220 Archers, 80 LC, 25 HC, 22 Rams, 12 Cats, 2 Nobles. Wall 12, Watchtower 8, Academy 1, Storage 14. Resources: W18,400 C16,200 I15,900 (Vault 3k). Queue: Stable training 15 LC (4m), Town Hall lvl 11 (12m). Loyalty 92. Recent command: outbound support to 415|420 (1m ago).`
- **Partial (Ranges):** `Recon Partial — Ashen Ford (333|478): Troops est. 900–1,200 mixed inf/cav; siege present (10–20). Wall ~10–12. Resources: High. Watchtower likely. 8/25 scouts lost.`
- **Trapped:** `Scouts Caught — Wolf Den (389|455): Traps triggered; your scouts were lost. Defender identified your origin (SunGuard / Rila). No intel gained.`
- **Misdirection:** `Report Suspect — Frost March (501|499): Intel shows 0 siege, low troops, high resources. Marker flagged: possible decoy (jammer active). Proceed with caution.`
- **Old Intel Display:** `Old Intel — Stonebrook (299|301) last scouted 26h ago: Troops 500–900 inf, Wall 8–11, Resources moderate. Recommend refresh.`
- **Ally Shared:** `Shared Scout — from Aeryn (Tribe): Targets for OP Dawn. Maple Hold (512|490) Wall 9, 700 Spears, 200 Archers; scouted 2h ago. Marker placed.`

## Implementation TODOs
- [ ] Scout duel logic: resolve offensive vs defensive scouts with watchtower/trap modifiers; return fidelity tier and casualties.
- [ ] Report fidelity tiers & decay: implement Fresh/Recent/Old/Stale transformations (exact → ranges/bands) and UI color codes; cron/job to age intel and fade markers.
- [ ] Traps/misdirection: consumable traps with limits; misdirection/false-report system with cooldowns and audit logs; enforce plausibility constraints.
- [ ] Intel sharing: shared report bank with tags/roles, map markers carrying intel metadata, and audit trails; redaction options for allies.
- [ ] Sector coverage: assign scouts to sectors; reminders for stale intel; surface coverage in tribe dashboard.
- [ ] Rate limits & anti-spam: cap scout commands per player/target/time window; diminishing returns on repeated failures; expose reason codes for UI.
- [ ] Anti-abuse/privacy: hash IP/UA in intel logs; forbid premium fidelity boosts; limit false reports per day; log all overrides.
