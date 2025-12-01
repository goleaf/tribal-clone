# Village Conquest & Control — Systems Design

## Conquest Overview
- Villages can change ownership through **loyalty/influence reduction**: attacks with special conquest units lower a village’s allegiance; when it hits 0 (or an influence bar fills), ownership transfers.
- Conquest is a multi-step commitment: soften defenses, breach walls, clear garrison/support, then deliver conquest payloads while defending against counter-support/snipes.
- Conquest is designed to be **costly, telegraphed, and contestable** to reward planning and counterplay.

## Special Conquest Unit/Mechanic — "Standard Bearer"
- **Lore/Role:** Elite heralds carrying a tribe’s war standard. They demoralize defenders and rally locals to switch allegiance.
- **Function:** On a successful attack, reduces **Allegiance** by a random band (e.g., 18–28) per surviving Standard Bearer. Allegiance at 0 or below transfers village if at least one Standard Bearer survives the wave.
- **Stats (design levers):**
  - Costly to train (Coins/Seals + high Wood/Clay/Iron + heavy population).
  - Slow travel (uses siege speed), vulnerable to all unit types, low combat stats.
  - Requires **Hall of Banners (Academy analog)** level N and minted standards (resource sink) to recruit.
- **Risks:** Expensive loss if intercepted; must be escorted by main army; travel time telegraphs intent.
- **Usage Patterns:** Noble trains (multiple waves seconds apart), split trains (spread over minutes to dodge snipes), or delayed last-wave to follow a fake-heavy opener.

## Capture Conditions
- **Preconditions:**
  - Attacking force must win the battle (defenders/support wiped or routed). Partial win does not apply allegiance damage.
  - At least one Standard Bearer survives the combat round.
  - Wall can stay up, but higher walls reduce Standard Bearer survival odds; optional rule: allegiance damage reduced if Wall > X.
  - Allegiance must reach 0 or below after the wave for capture.
- **Requirements to Field Standard Bearers:** Hall of Banners level, minted standards/coins available, sufficient population, and world may limit max per village.
- **Timing Constraints:** Command arrival order matters; allegiance damage resolves after casualties and wall damage in that wave.

## Number & Timing of Attacks
- **Typical Sequence:** 3–5 conquest waves depending on allegiance damage bands and regen.
  - Example: Starting Allegiance 100; four waves averaging -24 each capture if undefended.
- **Tactical Patterns:**
  - **Classic Train:** 4 waves spaced 100–300ms apart (desktop precision) to avoid snipes; risk of latency; countered by stacked defense and perfectly timed support.
  - **Split Train:** 2 waves close, pause for misdirection, 2 more later; used to bait defenses.
  - **Extended Pressure:** Continuous clearing attacks + sporadic conquest waves to force defender fatigue; relies on regen management.
  - **Fake Floods:** Many low-pop attacks to mask real Standard Bearer wave; countered by watchtower/intel filters.
- **Defense Options:**
  - **Sniping:** Support lands between conquest waves to raise defense and kill Bearers.
  - **Stacking:** Large support pre-stationed; high walls and trap layers.
  - **Dodge/Counter:** Defender pulls troops to avoid losses and counter-attacks attacker’s nobles village.
  - **Allegiance Regen Boosts:** Tribe tech or items increasing regen to extend number of required waves.

## Post-Capture Rules
- **Ownership:** Transfers to attacker; diplomacy state updates; command ownership transfers for stationed troops.
- **Buildings:**
  - Default: No building loss except wall damage from battle and targeted siege.
  - Variant: Random 0–1 building level loss on capture to represent chaos.
- **Troops:**
  - Defender troops present are destroyed or retreat if support from allies survives (world rule). Attacker’s surviving troops occupy village.
  - Stationed allied support either stays under new owner (if ally) or auto-returns (if hostile/neutral).
- **Resources:**
  - Remaining resources transfer to new owner; optional plunder step first reduces stock.
  - Vaulted/protected resources remain.
- **Allegiance After Capture:**
  - Sets to a low starting value (e.g., 25–35) to allow quick counter-capture if left undefended.
  - Regen resumes after short delay; can be modified by tribe tech/world rules.
- **Village Identity:** Name may persist or be auto-reset; attacker can rename. Tribe flag updates on map.
- **Cooldown:** Anti-snipe grace (e.g., 10–20 minutes) where allegiance cannot be reduced below a small buffer or incoming conquest waves get a debuff.

## Cool-downs & Limits
- **Anti-Rebound Timer:** Recently captured villages gain temporary allegiance floor (e.g., cannot drop below 10 for 15 minutes) to prevent ping-pong.
- **Per-Account Limits:** Soft cap on total villages per player; increasing maintenance or loyalty decay penalties when exceeding thresholds; hard cap optional on casual worlds.
- **Standard Bearer Limits:** Max per command and per village training queue; coin/standard minting limited per day to throttle spam.
- **Attack Rate Limits:** Minimum gap between successive conquest waves from same attacker on same target (e.g., 300ms server-enforced) to keep fair timing; longer on mobile-friendly worlds.

## Anti-Abuse Measures
- **Protection for Very Small Players:** Conquest blocked or heavily penalized when attacker power >> defender during beginner protection or below points threshold; morale floors apply to conquest units too.
- **Tribe-Internal Transfers:** Optional “handover” mode: tribe mates can reduce allegiance only if target opts-in (soft transfer) to prevent forced gifting; otherwise conquest vs tribe members disabled or taxed.
- **Multi-Account & Pushing:**
  - Diminishing returns and suspicion flags on repeated captures between same two accounts/tribes.
  - Cooldowns on recapturing a village you previously owned within short timeframe.
  - Resource/aid caps to reduce staged weak defenses.
- **Spawn/Protection Zones:** Conquest disabled in starter safe zones.
- **Report Transparency:** Conquest reports show morale, luck, and allegiance hits to spot anomalies.

## Capture Conditions Checklist (Example Table)
| Condition | Default Rule | Optional Variant |
| --- | --- | --- |
| Wall status | Any, but higher wall reduces survival; Rams advised | Require Wall <= X for allegiance damage |
| Defender cleared | Yes, must win combat | Partial losses still apply allegiance (riskier) |
| Conquest unit alive | At least one survives | Require majority to survive |
| Allegiance threshold | <= 0 to capture | Influence bar fill to 100% for capture |
| Cooldown after capture | Anti-snipe 10–20m | None on hardcore; longer on casual |

## Advanced & Optional Features
- **Vassalage:** Instead of full capture, attacker can impose vassal status: defender keeps village but pays tribute and cannot attack overlord; loyalty lowered but not zero. Breakable via revolt or support.
- **Shared Control:** Tribe leaders can set co-owners for frontline villages, allowing multiple players to issue commands (useful for sitter systems); logs track actions.
- **Temporary Occupation:** Occupy without full transfer for a duration (e.g., 12h), extracting resources and disabling production; ownership reverts after timer unless fully conquered.
- **Revolts/Uprisings:** Low-allegiance villages risk revolts, spawning militia and temporarily disabling production; attacker must restabilize. Defender can trigger revolt if they land a special “Liberate” command.
- **Influence Aura (Alternate System):** Holding adjacent villages projects influence; when influence outweighs defender’s, conquest waves do bonus allegiance damage. Encourages border wars.
- **Capitals/Ancients:** Special villages require more allegiance hits, have allegiance floors, or need simultaneous beacon control to capture.
- **Occupation Tax:** Recently captured villages produce less and pay extra upkeep until allegiance recovers; encourages defending, not just flipping.

## Player Tactics & Counters (Quick Notes)
- **Attackers:** Time waves tightly; clear support first; sync fakes to stretch defenses; protect origin villages from counter-noble snipes; watch allegiance regen tick.
- **Defenders:** Stack or snipe; boost allegiance regen; pre-queue traps; counter-attack noble villages; use watchtowers to spot fakes; rotate support to avoid overstack penalties (if any).

