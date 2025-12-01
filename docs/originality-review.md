# Originality Review — Medieval Tribal War Browser MMO

## Summary of the Submitted Design
- Persistent browser MMO where players develop villages, join tribes, and wage coordinated wars on a grid map.
- Economy built on three core resources plus population, coin minting for nobles, and event tokens; plunder/raiding drive growth.
- Building progression unlocks troop production (infantry/cav/siege), scouting, walls, and academy-minted nobles that reduce loyalty for conquest.
- Combat resolves with attack/defense comparisons, wall modifiers, morale, luck, night bonus, and support stacking; rams/catapults target walls/buildings.
- Map is tiled with continents/sectors, fog of war, barbarian/resource POIs, watchtowers, and relocation; movement speed depends on unit and terrain.
- Tribes coordinate via intel sharing, diplomacy states (ally/NAP/war), shared tech, resource aid, ops planning, and tribe quests.
- Worlds vary by speed, night bonus, hardcore/casual rules, monetization toggles, and endgame win conditions (dominance, relics, wonders, season points).
- NPE provides protection, guided tutorial, tribe matching, rebuild safety nets; events supply modifiers, PvE camps, token shops.
- Monetization is cosmetic-first with capped convenience; strong fair-play and anti-abuse policies.

## Generic vs. Specific Elements
- **Generic/genre-safe:**
  - Resource-based village growth with build queues and upgrade levels.
  - Three-resource economy plus population as troop cap.
  - Infantry/cavalry/siege archetypes; walls as defensive multipliers; rams/catapults reducing defenses.
  - Map-based travel times based on slowest unit; fog of war; scouting for intel; barbarian/NPC farming.
  - Tribe/clan social structure with roles, diplomacy states, shared chat/forums, and support mechanics.
  - World variations (speed, hardcore, casual), seasonal resets, and event-driven modifiers.
  - Fair-play policies, anti-botting, capped convenience monetization, battle pass without direct power.
- **Potentially specific/close to classic tribal war browser games:**
  - Naming patterns: “Nobleman,” “Academy,” “Barracks/Stable/Workshop,” “K-code/continent (K55),” “Barbarian villages,” “Night bonus,” “Coins” minted for nobles.
  - Loyalty-based capture with 0–100 loyalty and nobles reducing loyalty by a configured range (e.g., 20–35) until capture.
  - World “speed” terminology (1x/3x/5x) and morale formula protecting smaller players.
  - Ram vs. Wall level interactions and catapult targeting of specific buildings in battle resolution order.
  - Fake waves + noble trains + sniping/dodging timing meta, explicitly described.
  - Tribe tech as small % bonuses similar to known tribe skills/perks.
  - Watchtower as counter-scout structure with radius alerts.
  - Use of “Village” as the primary settlement type with expansion via capture rather than city founding.

## Risky Similarities (Why They Feel Derivative)
- **Nobleman + Loyalty (0–100, 20–35 drop per hit):** Mirrors iconic Tribal Wars conquest loop; identical numbers/flow risk trade dress similarity.
- **Coin Minting in Academy for Nobles:** Specific resource sink pattern taken from Tribal Wars; minting coins to create nobles is distinctive there.
- **Night Bonus / Morale Bands:** Recognizable defensive modifiers by time of day and points ratio; classic protection features.
- **K-code Naming (K55) & Continent Grid:** Same map nomenclature used in Tribal Wars; could imply lineage.
- **Barbarian Villages as Farm Targets:** Common but the “barbarian village” label plus early-game farming emphasis echoes TW starter loop.
- **Unit/Building Names:** Barracks, Stable, Workshop, Academy, Nobleman, Ram, Catapult, Watchtower — a near one-to-one roster.
- **Fake/Train/Snipe Timing Metagame:** Explicit callouts of “fake waves,” “noble trains,” “sniping” are lifted from community terminology.
- **World “Speed” and Luck/Morale Ranges:** Terms and ranges (e.g., luck -15%/+15%) map directly to TW defaults.
- **Dominance/Wonder/Relic Endgame Trio:** Very similar to later TW world variants with domination/WW-style objectives.

## Recommended Changes (More Original Alternatives)
- **Rename and Re-theme Conquest Loop:** Replace “Nobleman” with “Envoy,” “Chieftain,” or “Standard Bearer.” Swap loyalty with “Allegiance” or “Influence.” Capture requires stacking Influence points from envoy waves and maintaining “Control Uptime” after battle (e.g., a 10-minute control channel) instead of instant flip at 0.
- **Alter Currency Sink:** Replace coin minting with “Council Favors” earned via missions/events or crafted via “Mintmaster” building using seals; nobles/envoys cost seals + supplies, not minted coins.
- **Change Building Roster Names/Functions:**
  - Barracks → “Mustering Yard”; Stable → “Cavalry Grounds”; Workshop → “Siege Foundry”; Academy → “High Council Hall”; Watchtower → “Beacon Spire.”
  - Add differentiation functions (e.g., Beacon Spire projects early-warning flares consuming resin; Foundry queues modular siege parts that alter ram effects).
- **Modify Siege vs. Walls:** Instead of flat level drops, rams apply “Breach Severity” that reduces wall effectiveness temporarily; catapults apply damage-over-time to specific structures.
- **Revise Morale/Night Bonus:** Switch to “Supply Fatigue” that affects attackers traveling long distances; or “Garrison Resolve” that scales with recent defensive wins. Replace night bonus with “Weather Fronts” (fog, storms) that affect vision and projectile accuracy.
- **Map Nomenclature:** Replace K-codes with “Shires/Baronies” numbering (B3-S7). Use hex provinces or river valleys as natural regions instead of numbered continents.
- **Settlement Model:** Allow founding of “Outposts” (lightweight forward camps) in addition to capturing villages to reduce one-to-one mapping with TW’s village-only expansion.
- **Unit Terminology and Roles:** Rename core units (e.g., Spear → “Pikeneer,” Sword → “Shieldbearer,” LC → “Skirmisher Cav,” HC → “Lancer,” Scout → “Pathfinder,” Ram → “Battering Oxen,” Catapult → “Mantlet Thrower”). Mix in counter-abilities (e.g., Pathfinders can lay false tracks).
- **Event/Objective Differentiation:** Integrate relic crafting, weather manipulations, or migrating caravan POIs rather than static barb farms; avoid “barbarian village” label (use “Forsaken Hamlets” or “Frontier Camps”).
- **Timing Metagame Shift:** Reduce emphasis on noble trains by introducing a “Momentum” meter that penalizes multiple capture attempts within minutes, encouraging diversified ops rather than micro-timing snipes.
- **Luck/Morale Bands:** Use deterministic combat with revealed pre-battle calculators; replace luck with “Command Quality” earned via officer systems. If randomness stays, change range and source (e.g., “Terrain quirks” +/-8%).
- **Tribe Tech:** Make tech a drafting system (cards/edicts) that expire or rotate; avoid permanent % buffs mirroring TW tribe skills.
- **Endgame:** Swap Wonder with “Crown Council Trials” (rotating strategic tasks) or “Beacon Network” (activate and defend linked beacons), or “Kingmaking” elections where majority allegiance wins.

## Differentiation Features (Add 10–15 Distinctive Twists)
- Living weather fronts that alter vision/speed and can be forecast/manipulated via Beacon Spire projects.
- Influence-based conquest requiring post-battle control channeling and upkeep rather than instant loyalty drops.
- Officer system with assignable commanders granting tactical traits (e.g., Forced March, Defensive Lines) that level per world.
- Modular siege crafting (choose head/frame/ammo) to specialize vs walls, gates, or morale.
- Outpost/encampment system for forward staging with limited recruitment and supply lines.
- Dynamic river/road control points that provide speed and supply bonuses when held by a tribe.
- Rotating “Council Edicts” drafted by tribes each week that reshape rules (e.g., cavalry tax, siege truce) and grant prestige.
- Relic crafting trees producing map-wide buffs or vision artifacts, instead of just holding shrines.
- Caravan hijack mini-game for trade routes; defenders can set escort patterns and decoys.
- Seasonal beasts/raiders (PvE) that threaten regions, encouraging temporary alliances to repel them.
- Public war ledger with propaganda and reputation effects influencing morale/combat in neighboring sectors.
- Asymmetric clan roles: Raiders, Stewards, Sentinels — each with distinct passive bonuses and tribe quests.
- Terrain perks: forests boost skirmishers, hills buff archers/siege accuracy; players can terraform (clear forest/build roads) over time.
- Intel forgeries: spend inks to fake reports or mask troop counts, countered by pathfinder decryption.
- Score driven by “Stewardship” (economy), “Valor” (combat), and “Legitimacy” (control) to enable multiple win vectors.

## Conclusion & Action Items
- **Rename and re-theme** nobles, buildings, map regions, and key terms (night bonus, K-codes, barbarian villages) to avoid one-to-one parallels.
- **Adjust conquest mechanics** to use influence/control uptime and non-coin costs; alter luck/morale and siege behaviors.
- **Reframe world rules** (speed/morale wording) and community jargon (fake/train/snipe) into new terminology and mechanics.
- **Implement differentiation features** above to create clear distance from Tribal Wars-style precedents.
- Next steps: choose final naming set, lock conquest/siege revisions, update design doc accordingly, and run legal/IP pass after edits.
