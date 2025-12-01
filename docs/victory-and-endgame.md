# Victory Conditions & Endgame Design — Medieval Tribal War Browser MMO

## Win Condition Types (Mix-and-Match)
- **Dominance Percentage:** A tribe (or alliance-limited coalition) controls X% of villages or population within Y core regions for Z consecutive days. Variants: continent-specific dominance, province-lock dominance, or weighted dominance by village tier.
- **Relic/Ancient Village Control:** Hold a set of relic shrines, ancient capitals, or artifact villages that emit victory points per tick. Variants: rotating relic spawns, relics that must be socketed into “Great Works,” relics that migrate every 24h.
- **Victory Points (VP) Ladder:** Accrue VP from captures, relic control, successful defenses, event objectives, and tribe quests. Win when VP threshold reached or when season timer ends with highest VP.
- **Timed Seasons:** Fixed-duration worlds (e.g., 60–120 days). Winner is top VP tribe, or tribe holding key objectives at timer end. Encourages mid-season pacing and catch-up mechanics.
- **Multi-Stage Objectives:** Sequential phases (e.g., Gather Relics → Build Wonder → Defend Wonder) or province-locking stages (capture 3 provinces to unlock central wonder spawn).
- **Beacon Network / Wonder Construction:** Build and upgrade a world-unique structure requiring escalating resources, relics, and time. Construction progress is public; can be sabotaged via siege.
- **Capital Showdown:** Designated “Final Capitals” appear mid-map; tribes must capture and hold a set number. Capitals have higher defense slots, special defenses (e.g., extra wall aura), and decay timers if abandoned.
- **Hybrid Models:** Dominance + Relics (need both a % and relic control); Timer + Objective (highest VP but must own at least 1 relic); Wonder + Capital (finish wonder only while holding a capital tile).

## Endgame Triggers (Entering Final Phase)
- **Threshold Detection:** When any tribe reaches 60–70% of required dominance, or when relic spawns drop below a set number, or when season timer hits final 10–20%.
- **UI Signals:**
  - Global banner “Endgame Approaches: Dominance at 65%”
  - Map recolor/halo for endgame objectives; fog clears slightly around relic/wonder sites.
  - Dedicated endgame HUD bar showing objective control, VP race, and timer.
  - Sound/visual cues on first trigger; persistent header widget thereafter.
- **Announcements:** System mail + tribe leader ping; daily recap of standings; countdowns to critical spawns or lock-ins.
- **Map Events:** Spawn of final relics/ancients; locking of new player spawns; protection disabled for abandoned villages; movement speed debuffs near final objectives to cluster fights.

## Endgame Objectives (War Catalysts)
- **Great Wonder / Monument:** Multi-stage build (foundation, walls, spire). Each stage requires relics + escalating resource deliveries. Siege can regress progress within a window.
- **Beacon Network:** Place and link 5–7 beacons across provinces; must hold network simultaneously for N hours. Breaks if a beacon falls.
- **Relic Shrines:** Emit VP per tick; holding multiple grants bonus. Shrines have loyalty regeneration penalties to make them contestable; siege locks shrines for 30–60 minutes on capture to reduce ping-pong.
- **Ancient Capitals:** High-tier villages with inherent wall bonuses; provide region-wide buffs (vision/production) to holder. Must hold X of Y capitals to claim victory.
- **Crown Trials (Timed Challenges):** Rotating tasks (win 10 PvP battles in relic provinces within 24h; hold 3 shrines at daily reset). Points awarded per completed trial.
- **Province Locks:** Capture all villages in a province to “lock” it for 24h; locks grant VP multipliers but decay if not defended.
- **Sabotage Windows:** Attackers can trigger “Siege Windows” where wonder/beacon progress is vulnerable; defenders can schedule “Fortify Windows” to reduce incoming siege damage (limited uses).

## Post-Victory Phase
- **Cleanup/Mop-Up (48–72h):** World remains open but no new endgame progress; players can finish personal goals. Leaderboards freeze at declaration + 24h snapshot.
- **Free-for-All Option:** If configured, endgame locks objectives but keeps PvP open for bragging rights; no additional VP changes.
- **Museum Mode:** After closure, world becomes read-only spectator; battle reports and maps remain browsable for a set period; export options for tribe histories.
- **Immediate Shutdown (Speed Worlds):** On short worlds, closure happens within 24h of victory declaration; rewards auto-granted; data archived.

## Rewards (Non-P2W)
- **Tribe-Level:** Title (e.g., “Crown Tribe of World X”), tribe banner skin, forum theme, map marker skin, permanent entry in Hall of Fame.
- **Player-Level:** Profile titles, victory badges with world/year, unique report frames, cosmetic village/command trails, season points, small premium currency stipends, endgame portrait frames.
- **Participation Tokens:** For top contributors per tribe (captures, defenses, support sent) to prevent benchwarmers from full reward; clear contribution thresholds.
- **Runner-Up Rewards:** Silver/bronze badges, cosmetic variations, reduced currency stipends to keep morale for future seasons.
- **No Power Creep:** Rewards are cosmetic or QoL cosmetics (themes, frames). Any currency stipends respect existing daily/seasonal caps.

## Multiple Endgame Models (Pros/Cons)
- **Dominance Classic**
  - Pros: Simple, clear goal; familiar.
  - Cons: Can stalemate with turtle meta; steamroll if one tribe snowballs early.
- **Relic VP Race**
  - Pros: Encourages movement, spread fronts, counterplay; mid-tribes can snipe.
  - Cons: Requires careful spawn balance; may favor mobility over siege.
- **Wonder Construction**
  - Pros: Centralizes conflict; spectacle; clear progress bar.
  - Cons: Risk of tedium if siege loops; needs sabotage/decay to avoid camping.
- **Beacon Network (Simultaneous Hold)**
  - Pros: Forces multi-front coordination; great for large wars; reduces single-stack blobs.
  - Cons: Complex to teach; may frustrate small tribes.
- **Timed Season VP**
  - Pros: Predictable duration; good for competitive ladders; supports rotating modifiers.
  - Cons: Late runaway leads to checked-out players; needs catch-up and comeback mechanics.
- **Capital Showdown**
  - Pros: High drama; defensible objectives; clear rally points.
  - Cons: Risk of entrenched stalemate; requires anti-turtle mechanics (supply drain, siege windows).
- **Hybrid Dominance + Relics**
  - Pros: Prevents single-axis steamroll; forces both map control and objective play.
  - Cons: More rules to communicate; balancing weightings is tricky.

## Endgame Traps & Mitigations
- **Stalemates:** Add decay to locked provinces, siege windows, attrition on overstacked defenses, and relic migration.
- **Snowballing:** Implement comeback VP bonuses for underdogs on specific objectives, limit alliance size, and cap simultaneous support per objective.
- **Timezone Abuse:** Use rotating vulnerability windows configurable by holder within bounds; provide delayed-action siege that resolves at public times.
- **Mega-Coalitions:** Limit formal alliances; impose diminishing returns on support from multiple tribes; require minimum contribution for endgame credit.
- **Logistics Fatigue:** Introduce supply drain for armies stationed at endgame objectives beyond N hours; requires resupply convoys to maintain full strength.
- **Objective Camping:** Apply decay to wonder/beacon progress if not contested for long periods; spawn periodic “Quake” events that shake defenses and reset some progress unless actively defended.

## Example Endgame Scenarios
- **Scenario 1: Relic VP Race with Moving Shrines**
  - Endgame Trigger: Tribe Wolfbane hits 65% of dominance; shrines migrate to central provinces; UI banner appears with VP HUD.
  - Play: Wolfbane holds 3 shrines, rival IronFist holds 2. Shrines move every 12h; smaller tribe Emberwing snipes a migrating shrine using cavalry speed. VP swings as shrines migrate; siege windows on shrines allow IronFist to break Wolfbane’s hold. Final day, Wolfbane secures 4 shrines simultaneously for 6 hours, crossing VP threshold. Announcement fires; 48h cleanup begins.
- **Scenario 2: Wonder + Beacon Hybrid**
  - Trigger: After 75 days, wonder foundations spawn in central continent; beacon sites appear in surrounding provinces. UI shows wonder progress 0%.
  - Play: Tribe Dawnforge rushes beacons, linking 5/7 to activate build rights. Rival Ashen Pact repeatedly sabotages beacons to pause wonder build. Siege windows allow Ashen Pact to regress wonder stage 2 to stage 1. Smaller tribe Riverguard allies informally with Ashen Pact to hold one beacon; coalition penalty limits overstacking. After multi-front war, Dawnforge completes stage 3 while holding 5 beacons for required 8 hours. Victory declared; mop-up for 72h with open PvP.
- **Scenario 3: Timed Season VP with Crown Trials**
  - Trigger: Fixed 90-day season; final 10 days marked as “Final Trial.”
  - Play: VP earned from captures, relics, and daily Crown Trials (hold relic provinces at reset, win balanced-score battles). Tribe Silver Star leads but falters when underdog Oakshield wins multiple trials with coordinated defenses. Final day scoreboard updates hourly; Silver Star holds slight lead and defends key province locks. Timer ends; Silver Star wins by narrow VP margin. Rewards distributed; world shifts to museum mode for 14 days.
