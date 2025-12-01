# Combat System & Battle Resolution

## Combat Overview

The combat system is the heart of the game, where players test their military might against each other. Every battle is resolved server-side using a tick-based system that processes all incoming and outgoing troop movements at regular intervals.

### Core Combat Principles

Combat resolution follows these fundamental principles:

- **Power Calculation**: Each unit type has base attack and defense values. The total attacking power is compared against total defending power to determine casualties and outcomes.
- **Asymmetric Warfare**: Attackers have advantages in mobility and surprise; defenders benefit from walls, terrain, and preparation time.
- **Resource Stakes**: Successful attacks yield plundered resources; failed attacks waste time and troops.
- **Information Warfare**: Scouting, fakes, and deception are as important as raw military strength.
- **Time-Based Strategy**: Movement speed, arrival timing, and coordination windows create strategic depth.

### Battle Resolution Factors

When troops clash, the outcome depends on:

- **Troop Composition**: Infantry, cavalry, ranged units, siege weapons, and special units each have strengths and weaknesses
- **Wall Fortifications**: Defensive structures absorb damage and multiply defender effectiveness
- **Terrain Modifiers**: Hills, forests, rivers, and plains affect movement and combat differently
- **Morale System**: Troop confidence based on recent victories, losses, and relative strength
- **Luck Variance**: Random factors that prevent perfect predictability and help underdogs
- **Time of Day**: Night attacks may suffer penalties or gain stealth advantages
- **Weather Conditions**: Rain, snow, fog affecting visibility and unit effectiveness
- **Defender Preparation**: Whether defenders had warning and time to organize
- **Support Stacking**: Multiple players defending together combine their forces
- **Loyalty Status**: Villages with low loyalty are easier to conquer but harder to defend

### Conceptual Battle Flow

1. **Attacker sends troops** with a specific mission type and target
2. **Movement phase** where troops travel across the map at their speed
3. **Arrival detection** where defenders may spot incoming attacks
4. **Pre-battle preparation** where defenders can call for support or dodge
5. **Battle resolution tick** where the server calculates the outcome
6. **Damage calculation** applying all modifiers and determining casualties
7. **Resource plunder** if the attacker wins
8. **Battle reports** sent to all involved parties
9. **Aftermath effects** including morale changes, loyalty shifts, and troop returns

---

## Attack Types

The game supports multiple attack modes, each with distinct purposes and mechanics:

### Normal Attack

The standard offensive action where troops are sent to destroy enemy forces and plunder resources.

- **Purpose**: Kill defenders, steal resources, weaken enemy villages
- **Mechanics**: Full combat resolution with casualties on both sides
- **Plunder**: Attacker can carry back resources up to their carrying capacity
- **Wall Damage**: Siege weapons reduce wall levels during the attack
- **Duration**: Troops return home after battle, travel time doubled (there and back)
- **Visibility**: Defenders see incoming attacks based on watchtower level and distance
- **Best Used**: When you have military superiority and want to profit from raids

### Raid (Quick Strike)

A faster, lighter attack focused on grabbing resources with minimal engagement.


- **Purpose**: Fast resource theft with minimal risk
- **Mechanics**: Combat occurs but raiders may flee if resistance is too strong
- **Plunder**: Higher carrying capacity bonus for raids (e.g., 125% normal capacity)
- **Speed**: Faster movement speed (e.g., 150% of normal attack speed)
- **Casualties**: Raiders take reduced casualties if they successfully retreat
- **Limitations**: Cannot conquer villages or significantly damage walls
- **Best Used**: Hit-and-run tactics against weaker or undefended villages

### Siege Attack

A prolonged assault designed to break through heavy fortifications and conquer villages.

- **Purpose**: Destroy walls, conquer villages, establish permanent control
- **Mechanics**: Extended battle with multiple phases focusing on wall destruction
- **Requirements**: Must include siege weapons (catapults, rams, trebuchets)
- **Wall Interaction**: Siege weapons deal massive damage to walls before troops engage
- **Conquest**: If attacker wins and brings a noble/chieftain, can lower loyalty
- **Duration**: Slower movement speed due to heavy siege equipment
- **Visibility**: Highly visible due to large army size and slow movement
- **Best Used**: Final conquest attempts or breaking heavily fortified positions

### Support Movement (Reinforcement)


Sending defensive troops to help an ally or your own village defend against attacks.

- **Purpose**: Strengthen defenses of friendly villages
- **Mechanics**: Troops station at target village and defend against all incoming attacks
- **Duration**: Troops remain until manually recalled or killed in battle
- **Stacking**: Multiple players can support the same village, combining defenses
- **Command**: Supporting player retains control and can recall troops anytime
- **Visibility**: Defenders see supported troops in their village; attackers don't know
- **Resource Consumption**: Supported troops consume food from the host village
- **Best Used**: Coordinated tribal defense, protecting key players, mutual defense pacts

### Scouting Mission

Intelligence gathering to reveal enemy village information without full combat.

- **Purpose**: Gather information about enemy troops, resources, and defenses
- **Mechanics**: Scouts attempt to infiltrate and report back without being detected
- **Detection**: Defender's scouts can intercept and kill attacking scouts
- **Information Revealed**: Depends on scout survival rate and defender's counter-scouts
- **Speed**: Fastest movement type in the game
- **Casualties**: Scouts die if detected; some may escape with partial information
- **Report Levels**: 
  - Full success: All troops, resources, buildings visible
  - Partial success: Approximate troop counts, some building info
  - Failure: Scouts killed, no information gained, defender alerted
- **Best Used**: Before planning major attacks, checking if defenses have left



### Fake Attack (Feint)

A deceptive movement designed to mislead defenders without actual combat intent.

- **Purpose**: Distract defenders, force them to commit troops, mask real attacks
- **Mechanics**: Troops are sent but automatically return before arrival
- **Visibility**: Appears identical to real attack in defender's incoming list
- **Cancellation**: Troops turn around at a predetermined point (e.g., 80% of journey)
- **Cost**: Only travel time and potential opportunity cost, no casualties
- **Detection**: Defenders cannot distinguish fakes from real attacks until troops return
- **Variations**:
  - **Early fake**: Returns very early, minimal time investment
  - **Deep fake**: Returns just before arrival, maximum deception
  - **Mixed fake**: Some troops are fake, others continue as real attack
- **Best Used**: Overwhelming defender's attention, forcing defensive mistakes, coordinating with real attacks

### Mass Coordinated Attack

Multiple players launching synchronized strikes against a single target.

- **Purpose**: Overwhelm defenses through sheer numbers and timing
- **Mechanics**: Separate attacks from different players timed to arrive simultaneously
- **Coordination**: Requires precise timing and communication between attackers
- **Resolution**: Each attack is resolved separately but in rapid succession

- **Defender Challenge**: Must split defenses or choose which attack to defend against
- **Timing Window**: Attacks within same server tick are considered simultaneous
- **Best Used**: Taking down heavily defended targets, tribal warfare operations

### Noble/Chieftain Conquest

Special attack type that reduces village loyalty and enables conquest.

- **Purpose**: Transfer village ownership from defender to attacker
- **Requirements**: Must win battle and have noble/chieftain unit survive
- **Loyalty Reduction**: Each successful noble attack reduces loyalty by fixed amount
- **Conquest Threshold**: Village is conquered when loyalty reaches zero
- **Multiple Attempts**: Usually requires several noble attacks to fully conquer
- **High Risk**: Nobles are expensive and vulnerable; losing them is devastating
- **Timing**: Often sent as follow-up after clearing attacks destroy defenses
- **Best Used**: Final stage of conquest operations after defenses are eliminated

### Clearing Attack (Nuke)

Massive offensive force designed to completely eliminate all defenders.

- **Purpose**: Destroy all defending troops to enable safe follow-up attacks
- **Composition**: Large armies with balanced unit types for maximum killing power
- **Casualties**: Attacker expects heavy losses but aims to kill all defenders
- **Follow-up**: Clears the way for nobles, raids, or occupation
- **Timing**: Must be coordinated with follow-up attacks arriving shortly after

- **Resource Plunder**: Secondary concern; main goal is troop elimination
- **Best Used**: Conquest operations, eliminating dangerous defensive stacks

### Snipe Attack

Precisely timed attack designed to arrive in a specific server tick to exploit timing gaps.

- **Purpose**: Hit between defender's troop movements or support arrivals
- **Timing**: Requires exact calculation of arrival time down to the second
- **Mechanics**: Exploits gaps when defenses are in transit or just left
- **Skill Required**: High level of game knowledge and timing precision
- **Counter**: Defenders must maintain constant presence or hide troop movements
- **Best Used**: Advanced players exploiting defensive weaknesses, dodging counter-attacks

### Pillage and Burn

Destructive attack focused on damaging buildings and infrastructure.

- **Purpose**: Cripple enemy economy by destroying production buildings
- **Mechanics**: After winning battle, attacker's troops damage random buildings
- **Building Damage**: Buildings lose levels, requiring reconstruction
- **Resource Cost**: Expensive for defender to rebuild damaged infrastructure
- **Psychological Impact**: Demoralizes players and disrupts long-term planning
- **Limitations**: Cannot destroy certain buildings (e.g., headquarters below level 1)
- **Best Used**: Vendetta warfare, crippling enemy production capacity



### Escort Mission

Protecting resource traders or settlers moving between villages.

- **Purpose**: Defend vulnerable non-combat units during transit
- **Mechanics**: Military units travel alongside traders/settlers
- **Combat**: If intercepted, escorts fight to protect the cargo
- **Speed**: Moves at speed of slowest unit in the group
- **Best Used**: Protecting valuable resource transfers, settler movements

### Ambush/Intercept

Attacking enemy troops while they're in transit between villages.

- **Purpose**: Destroy enemy armies before they reach their destination
- **Mechanics**: Requires intelligence about enemy troop movements
- **Timing**: Must calculate intercept point and send troops to meet them
- **Advantage**: Catch enemies without wall protection or defensive bonuses
- **Difficulty**: Requires precise timing and movement prediction
- **Best Used**: Advanced tactical play, disrupting enemy operations

### Tribal War Operations

Large-scale coordinated attacks involving entire tribes.

- **Purpose**: Systematic conquest or destruction of enemy tribe
- **Coordination**: Requires tribal leadership, planning, and communication
- **Phases**: 
  - Intelligence gathering across multiple targets
  - Coordinated clearing waves
  - Noble trains for conquest
  - Support operations to hold conquered villages

- **Duration**: Can last days or weeks of real-time
- **Best Used**: Endgame tribal dominance, territorial expansion

---

## Pre-Battle Phase

The period between attack launch and battle resolution is critical for both attackers and defenders.

### Attacker Pre-Battle Actions

**Planning and Preparation:**

- **Target Selection**: Choose villages based on resources, defenses, location, and strategic value
- **Intelligence Gathering**: Scout target to assess defenses, resources, and troop presence
- **Timing Calculation**: Determine optimal arrival time considering defender's timezone, activity patterns
- **Force Composition**: Select unit mix based on expected defenses and mission objectives
- **Fake Coordination**: Send fake attacks to confuse and overwhelm defender
- **Wave Planning**: Organize multiple attacks in sequence (clearing, nobles, follow-ups)
- **Support Coordination**: Arrange for tribal members to send supporting attacks
- **Resource Management**: Ensure enough resources to rebuild troops after expected casualties

**During Movement:**

- **Monitoring**: Watch for defender's online status and activity
- **Adjustment**: Cannot change attack once launched, but can plan follow-ups
- **Communication**: Coordinate with tribe members on timing and targets
- **Backup Planning**: Prepare alternative attacks if primary target shows unexpected strength


### Defender Pre-Battle Actions

**Detection and Assessment:**

- **Incoming Attack Visibility**: Depends on watchtower level and attack distance
  - **No watchtower**: See attacks only 30 minutes before arrival
  - **Low watchtower**: See attacks 2-4 hours before arrival
  - **High watchtower**: See attacks 6-12 hours before arrival
  - **Maximum watchtower**: See attacks immediately when launched
- **Attack Information**: Defender sees:
  - Origin village and player name
  - Arrival time (exact to the second)
  - Attack type (if distinguishable)
  - Approximate army size (small, medium, large, massive) based on watchtower level
  - Unit composition (only if scouts are present and survive)

**Defensive Options:**

- **Stand and Fight**: Keep troops in village and defend normally
- **Call for Support**: Request tribal reinforcements to arrive before attack
- **Dodge**: Send troops away temporarily, returning after attack passes
- **Counter-Attack**: Launch attacks against attacker's villages as retaliation
- **Resource Hiding**: Send resources away to prevent plunder
- **Emergency Recruitment**: Train additional troops if time permits
- **Wall Repair**: Upgrade or repair walls before attack arrives
- **Trap Setting**: Some game variants allow defensive traps or ambush mechanics



**Dodging Mechanics:**

Dodging is a defensive tactic where troops are sent away before an attack arrives and return afterward.

- **Execution**: Send troops to a distant target (real or fake) timed to be away during attack
- **Timing**: Must calculate precisely when troops leave and return
- **Risk**: If timing is wrong, troops may be present during attack or absent too long
- **Counter**: Attackers can send follow-up attacks or snipes to catch returning troops
- **Resource Vulnerability**: Dodging saves troops but leaves resources exposed to plunder
- **Fake Dodge**: Pretend to dodge but actually keep troops home to surprise attacker
- **Partial Dodge**: Send some troops away, keep others to defend

**Support Coordination:**

- **Tribal Support**: Request reinforcements from tribe members
- **Support Timing**: Ensure support arrives before attack, not during or after
- **Communication**: Use in-game messaging, external chat, or forums to coordinate
- **Support Stacking**: Multiple supporters can send troops to same village
- **Support Visibility**: Attacker doesn't see supported troops until battle
- **Support Management**: Defender must track which troops belong to which supporter

### Scouting and Intelligence

**Attacker Scouting:**

- **Pre-Attack Scouts**: Send scouts before main attack to assess defenses
- **Scout Timing**: Scouts should arrive and return before main attack launches
- **Information Decay**: Defender may change troop positions after scout

- **Scout Sacrifice**: Some attackers send scouts with main attack to reveal defenses in battle report
- **Counter-Scouting**: Defenders with scouts can kill attacking scouts before they report

**Defender Counter-Intelligence:**

- **Scout Presence**: Maintaining scouts in village to intercept enemy scouts
- **Information Denial**: Kill enemy scouts to prevent intelligence gathering
- **Deception**: Move troops around to confuse attackers who scouted earlier
- **Fake Weakness**: Appear weak to lure attacks into traps
- **Fake Strength**: Appear strong to deter attacks

### Fake Attack Strategy

Fakes are a critical component of advanced warfare, creating confusion and forcing defensive errors.

**Fake Attack Purposes:**

- **Attention Overload**: Send dozens of fakes to hide real attacks among them
- **Resource Drain**: Force defender to call expensive support for fake threats
- **Timing Exploitation**: Make defender commit troops to wrong timing window
- **Psychological Warfare**: Stress and exhaust defender with constant threats
- **Support Baiting**: Reveal which tribe members support the target
- **Pattern Breaking**: Disrupt defender's ability to predict real attack timing

**Fake Attack Execution:**

- **Volume**: Send many fakes from multiple villages
- **Timing Variation**: Spread fakes across different arrival times
- **Mixed Composition**: Vary army sizes to make some fakes look more credible

- **Real Attack Hiding**: Bury 1-3 real attacks among 20-50 fakes
- **Cancellation Timing**: Cancel fakes at different points to maintain uncertainty
- **Cost Efficiency**: Use minimum troops needed to appear threatening

**Defender Fake Detection:**

- **Pattern Recognition**: Experienced defenders learn to identify fake patterns
- **Statistical Analysis**: Track attacker's historical fake-to-real ratio
- **Timing Analysis**: Real attacks often arrive at specific times (e.g., when defender sleeps)
- **Army Size**: Very small or very large armies may indicate fakes
- **Origin Analysis**: Attacks from distant villages are more likely real (higher cost)
- **Gut Feeling**: Intuition based on game experience and attacker behavior

**Counter-Fake Strategies:**

- **Ignore All**: Risky but saves resources; hope to survive real attacks
- **Defend All**: Expensive but safe; maintain constant defensive presence
- **Selective Defense**: Defend only most credible threats based on analysis
- **Fake Support**: Pretend to call support to make attacker think you're scared
- **Counter-Fake**: Send your own fakes back to distract attacker

---

## Battle Resolution Logic

When an attack arrives at its target, the server processes the battle through several distinct phases.

### Phase 1: Pre-Battle Setup

**Troop Assembly:**


- System identifies all attacking troops and their stats
- System identifies all defending troops (village owner + all supporters)
- System checks for any special units (nobles, heroes, champions)
- System verifies all troops are valid and not in transit

**Modifier Calculation:**

- Determine terrain type of defender's village (plains, hills, forest, etc.)
- Check time of day (day, night, dawn, dusk) and apply relevant modifiers
- Calculate weather effects if weather system is active
- Determine wall level and wall defensive bonus
- Calculate morale modifier based on attacker and defender village points
- Generate luck factor (random variance within acceptable range)
- Apply any active special effects (blessings, curses, seasonal events)

**Information Gathering:**

- Record all relevant data for battle report generation
- Snapshot defender's resources for potential plunder calculation
- Note any special conditions (first attack on village, revenge attack, etc.)

### Phase 2: Wall Interaction

If the attack includes siege weapons, walls are engaged before main combat.

**Wall Damage Phase:**

- Siege weapons (rams, catapults, trebuchets) attack wall structure
- Wall has defensive value that reduces siege weapon effectiveness
- Each siege weapon type has different wall damage potential
- Rams are most effective against walls, catapults less so

- Defenders can have defensive siege weapons that counter-attack
- Wall damage is calculated and wall level may be reduced
- Remaining wall level determines defensive bonus for main combat

**Wall Defensive Bonus:**

- Walls multiply the defensive power of defending troops
- Higher wall levels provide exponentially better defense
- Wall bonus applies to all defending troops, including supporters
- Wall bonus is reduced or eliminated if heavily damaged by siege weapons
- Some unit types (cavalry) may receive reduced benefit from walls
- Infantry and ranged units receive full wall defensive bonus

### Phase 3: Main Combat Resolution

**Power Calculation:**

The core of battle resolution compares total attacking power against total defending power.

- Each attacking unit contributes its attack value to total attack power
- Each defending unit contributes its defense value to total defense power
- Unit-specific bonuses apply (cavalry vs. archers, spearmen vs. cavalry, etc.)
- Terrain modifiers adjust unit effectiveness
- Wall bonus multiplies defender's power
- Morale modifier adjusts attacker's power
- Luck factor adds random variance to both sides

**Combat Rounds:**

Battle proceeds in conceptual rounds until one side is eliminated or retreats.


- Each round, both sides deal damage to each other
- Damage is distributed across enemy units based on their defensive/offensive values
- Units with lower health die first
- Casualties accumulate each round
- Battle continues until one side is completely eliminated or combat ends

**Damage Distribution:**

- Damage is not evenly distributed; weaker units die first
- Each unit type has a health/hitpoint value
- Damage is allocated proportionally based on unit presence and vulnerability
- Some units may be specifically targeted (e.g., siege weapons prioritized)
- Random variance ensures not perfectly predictable outcomes
- Elite units may have survival bonuses

**Victory Determination:**

- **Attacker Victory**: All defenders eliminated, attacker has surviving troops
- **Defender Victory**: All attackers eliminated, defender has surviving troops
- **Mutual Destruction**: Both sides eliminated (rare, counts as defender victory)
- **Retreat**: In raid scenarios, attacker may retreat if losses exceed threshold

### Phase 4: Post-Combat Effects

**Casualty Finalization:**

- Dead troops are removed from the game permanently
- Wounded troops may return home with reduced numbers
- Some game variants have "hospital" mechanics where some casualties can be healed
- Surviving troops are recorded for return journey or continued presence



**Resource Plunder (Attacker Victory):**

- Attacker can plunder resources up to their carrying capacity
- Carrying capacity is sum of all surviving troops' carry values
- Resources are taken in order of priority (configurable: gold > food > wood > stone)
- Plundered resources are carried back to attacker's village
- Defender loses the plundered resources immediately
- If defender has less resources than carry capacity, attacker takes all available
- Some buildings (hiding places, vaults) can protect a portion of resources from plunder

**Loyalty Reduction (Noble Attacks):**

- If attacker brought a noble/chieftain and won the battle
- Noble must survive the battle to reduce loyalty
- Loyalty is reduced by a fixed amount (e.g., 20-30 points per noble)
- If loyalty reaches zero, village ownership transfers to attacker
- Loyalty reduction is independent of battle outcome margin
- Failed noble attacks (where noble dies) waste expensive unit

**Building Damage:**

- If attacker wins and has special destructive units or abilities
- Random buildings may lose levels
- Critical buildings (headquarters, wall) may have damage caps
- Building damage is expensive for defender to repair
- Some attack types specifically target buildings (pillage and burn)

**Wall Degradation:**


- Wall damage from siege weapons is applied
- Wall level may be reduced by 1-3 levels depending on siege weapon count
- Damaged walls must be rebuilt, costing resources and time
- Lower walls make future attacks more successful

### Phase 5: Aftermath and Returns

**Troop Return Journey:**

- Surviving attacking troops begin return journey to home village
- Return journey takes same time as outbound journey
- Troops carry plundered resources back home
- Troops are vulnerable during return (can be intercepted in some variants)
- Defender's troops remain in place (or return if they were supporters)

**Morale Updates:**

- Winner gains morale boost for future battles
- Loser suffers morale penalty
- Morale effects decay over time
- Extreme victories/defeats have larger morale impacts

**Experience and Leveling:**

- Surviving troops may gain experience points
- Experienced troops become more effective in future battles
- Heroes or champion units gain significant experience
- Experience can unlock special abilities or stat bonuses

**Report Generation:**

- Detailed battle reports are created for attacker and defender
- Reports include troop counts, casualties, resources plundered, and battle outcome
- Reports are stored in player's message inbox
- Supporters receive reports about their troops' performance



---

## Special Tactics and Advanced Strategies

### Nuke and Clear Operations

A "nuke" or "clear" is a massive attack designed to completely eliminate all defending forces.

**Nuke Characteristics:**

- Extremely large army, often 80-100% of attacker's total military
- Balanced composition to counter all defender unit types
- Primary goal is killing defenders, not plundering resources
- Attacker expects to lose 50-80% of attacking force
- Success means zero or near-zero defenders remain

**Nuke Timing:**

- Must be coordinated with follow-up attacks (nobles, raids)
- Follow-ups should arrive 1-5 minutes after nuke
- Gap must be small enough that defender can't retrain or receive support
- Gap must be large enough that nuke casualties are processed first

**Multi-Nuke Strategy:**

- Send multiple clearing attacks in sequence
- First nuke kills most defenders
- Second nuke kills any survivors or newly arrived support
- Third nuke (if needed) ensures complete clearing
- Each subsequent nuke is smaller as fewer defenders remain

### Noble Trains

A "noble train" is a sequence of noble attacks designed to conquer a village.



**Noble Train Structure:**

- First attack: Massive clearing nuke to eliminate all defenders
- Second attack: First noble with small escort (arrives 1-2 minutes after nuke)
- Third attack: Second noble with small escort (arrives 1-2 minutes after first noble)
- Fourth attack: Third noble with small escort (if needed)
- Continue until loyalty reaches zero and village is conquered

**Noble Train Timing:**

- Precise timing is critical; gaps allow defender to recover
- Nobles must arrive after defenses are cleared but before support arrives
- Each noble reduces loyalty by fixed amount (e.g., 25 points)
- Village starts at 100 loyalty, so typically need 4 nobles to conquer
- If any noble dies, must send replacement, delaying conquest

**Noble Train Defense:**

- Defender must kill nobles to prevent loyalty loss
- Even small defensive forces can kill lightly-escorted nobles
- Defender can send support timed to arrive between nobles
- Defender can dodge the nuke but return for nobles
- Killing nobles is devastating to attacker (nobles are very expensive)

### Sniping Techniques

Sniping is the art of timing attacks to exploit brief defensive vulnerabilities.

**Snipe Scenarios:**

- **Support Gap Snipe**: Attack between when old support leaves and new support arrives
- **Dodge Return Snipe**: Attack when defender's troops are returning from dodge

- **Offline Snipe**: Attack when defender logs off and can't react
- **Troop Movement Snipe**: Attack when defender's troops are away attacking someone else
- **Support Recall Snipe**: Attack immediately after supporter recalls their troops

**Snipe Execution:**

- Requires precise knowledge of defender's troop movements
- Must calculate exact arrival time down to the second
- Often uses fastest troops (cavalry) for maximum timing flexibility
- May send multiple snipes at different times to catch any gap
- High risk: if timing is wrong, attack hits full defenses

**Counter-Sniping:**

- Maintain constant defensive presence (never leave village empty)
- Stagger support arrivals so there's always overlap
- Use fake troop movements to mislead snipers
- Keep some troops hidden (don't show full strength in scouts)
- Randomize troop movement patterns to prevent prediction

### Defensive Stacking

Stacking is when multiple players send defensive troops to the same village.

**Stacking Benefits:**

- Combines defensive power of multiple players
- Allows smaller players to contribute to defense
- Creates defensive strongpoints that are very difficult to break
- Enables mutual protection within tribes
- Discourages attacks through overwhelming defense



**Stacking Strategies:**

- **Permanent Stack**: Supporters leave troops indefinitely
- **Rotating Stack**: Supporters take turns, ensuring constant presence
- **Emergency Stack**: Supporters send troops only when attacks are incoming
- **Fake Stack**: Supporters send small amounts to appear stronger than reality
- **Hidden Stack**: Supporters send troops that attacker doesn't know about

**Stacking Challenges:**

- Coordination requires communication and trust
- Supported troops consume food from host village
- If village falls, all stacked troops are lost
- Supporters can recall troops anytime, leaving gaps
- Large stacks are expensive to maintain

**Breaking Stacks:**

- Send massive coordinated attacks from multiple players
- Use fakes to force stack to commit, then snipe when they leave
- Attack supporter's home villages to force them to recall troops
- Outlast the stack (supporters eventually need troops elsewhere)
- Diplomatic pressure or bribes to break tribal unity

### Backtime Operations

Backtiming is attacking an enemy while their troops are away attacking you or someone else.

**Backtime Concept:**

- Enemy sends attack to your village
- You calculate when their troops will return home
- You send attack to their village timed to arrive while their troops are away
- Their village is defenseless, allowing easy plunder or conquest



**Backtime Execution:**

- Note exact arrival time of enemy attack on your village
- Calculate their return time (arrival time + travel time)
- Send your attack to arrive at their village during their return journey
- Timing must be precise; early or late hits their defenses
- Can backtime multiple villages if enemy attacks from several locations

**Backtime Defense:**

- Keep defensive troops in multiple villages
- Don't send all troops from one village
- Use support from allies to defend while your troops are away
- Send fakes instead of real attacks to avoid exposing yourself
- Stagger attack timing so troops return at different times

### Fake Nuking

Fake nuking is sending what appears to be a clearing attack but is actually a fake or much smaller force.

**Fake Nuke Purpose:**

- Force defender to call expensive support for a non-threat
- Exhaust defender's tribal support availability
- Make defender waste time and attention
- Set up real nuke by making defender complacent after several fakes
- Psychological warfare to stress and confuse defender

**Fake Nuke Execution:**

- Send attack that looks like nuke (large army size indicator)
- Cancel attack before arrival or send much smaller force than expected
- Mix real and fake nukes so defender can't tell which is which
- Send from multiple villages to appear more credible


- Time fake nukes to arrive when defender is likely offline

### Conquest Chains

Conquest chains are systematic sequences of village conquests, where each conquered village becomes a base for conquering the next.

**Chain Conquest Strategy:**

- Conquer village A near your territory
- Use village A as forward base to attack village B
- Conquer village B, now deeper in enemy territory
- Use villages A and B to attack village C
- Continue chain, expanding territory systematically

**Chain Benefits:**

- Reduces travel time for subsequent attacks
- Creates supply lines and forward bases
- Allows deeper penetration into enemy territory
- Establishes territorial control, not just village ownership
- Intimidates enemies and demonstrates power

**Chain Risks:**

- Newly conquered villages are vulnerable (low loyalty, damaged walls)
- Requires significant resources to rebuild and defend new villages
- Overextension can leave supply lines vulnerable
- Enemy can counter-attack and break the chain

### Timing Defense

Timing defense is the practice of moving defensive troops to arrive exactly when needed.

**Timing Defense Concept:**


- Instead of keeping troops permanently in village, send them to arrive just before attack
- Troops spend minimal time away from home village
- Reduces food consumption and opportunity cost
- Allows same troops to defend multiple villages in sequence

**Timing Defense Execution:**

- See incoming attack with arrival time
- Calculate when to send support to arrive 1-5 minutes before attack
- Send support from nearby village with precise timing
- Support arrives, defends against attack, then returns home
- Can immediately send same troops to defend another village

**Timing Defense Risks:**

- If calculation is wrong, support arrives too late (after attack)
- Attacker can snipe the gap before support arrives
- Requires constant attention and online presence
- Vulnerable to multiple simultaneous attacks

### Village Sitting

Village sitting is when another player temporarily controls your account to defend while you're offline.

**Sitting Mechanics:**

- Grant trusted player (usually tribe member) access to your account
- Sitter can move troops, call support, and manage defenses
- Sitter cannot spend resources, recruit troops, or make permanent changes
- Sitting is essential for 24/7 defense in real-time games



**Sitting Strategy:**

- Arrange sitters in different timezones for 24/7 coverage
- Sitters defend during your sleep or work hours
- Sitters can coordinate tribal defense operations
- Sitters provide continuity during vacations or emergencies

**Sitting Risks:**

- Must trust sitter completely (they have account access)
- Sitter mistakes can cost you troops or villages
- Some games prohibit or restrict sitting
- Sitter may not play as well as you would

---

## Morale and Luck Systems

Morale and luck are optional mechanics that add variance and balance to combat.

### Morale System

Morale represents the psychological state and confidence of attacking troops.

**Morale Concept:**

- Morale is a percentage modifier applied to attacker's power
- Morale is based on relative strength between attacker and defender
- Attacking much weaker players reduces morale (discourages bullying)
- Attacking similar or stronger players maintains full morale
- Morale affects only attacker, never defender

**Morale Calculation Options:**

**Option 1: Village Points Based**
- Compare attacker's village points to defender's village points
- If attacker has 10x more points, morale might be 50%
- If attacker has equal or fewer points, morale is 100%

- Graduated scale: 2x points = 90% morale, 5x = 70%, 10x = 50%, etc.

**Option 2: Player Points Based**
- Compare total points of attacking player to defending player
- Accounts for player's overall strength, not just one village
- Prevents large players from easily farming small players
- More fair but more complex to calculate

**Option 3: Recent Battle History**
- Morale increases with victories, decreases with defeats
- Winning streak gives morale bonus
- Losing streak gives morale penalty
- Resets over time or after certain events

**Option 4: Distance Based**
- Attacking nearby villages has full morale
- Attacking distant villages reduces morale (supply line strain)
- Encourages local warfare and territorial cohesion
- Discourages cross-map attacks

**Option 5: Hybrid System**
- Combines multiple factors (points, distance, history)
- Weighted formula balances different considerations
- Most realistic but most complex

**Morale Tuning Parameters:**

- **Minimum Morale**: Lowest morale can drop (e.g., 30% or 50%)
- **Morale Curve**: How quickly morale decreases with point difference
- **Morale Scope**: Village-based, player-based, or tribe-based
- **Morale Exceptions**: Certain attack types ignore morale (e.g., tribal wars)



**Morale Impact on Gameplay:**

- Protects new and small players from being farmed by large players
- Encourages attacking players of similar strength
- Creates more balanced and competitive warfare
- May frustrate large players who want to dominate small players
- Can be exploited (small players attacking large players with full morale)

### Luck System

Luck introduces random variance to battle outcomes, preventing perfect predictability.

**Luck Concept:**

- Luck is a random modifier applied to both attacker and defender
- Luck varies within a defined range (e.g., -25% to +25%)
- Luck is rolled independently for each battle
- Luck affects both sides, creating unpredictable outcomes
- Luck helps underdogs occasionally win against superior forces

**Luck Implementation Options:**

**Option 1: Simple Random Modifier**
- Each side gets random luck between -25% and +25%
- Applied to total attack/defense power
- Completely random, no skill involved
- Maximum variance, maximum unpredictability

**Option 2: Gaussian Distribution**
- Luck follows bell curve (normal distribution)
- Most battles have luck near 0% (average)
- Extreme luck (+25% or -25%) is rare
- More predictable than simple random, but still variable



**Option 3: Underdog Bonus**
- Weaker side gets positive luck bias
- Stronger side gets negative luck bias or neutral luck
- Helps smaller players compete against larger players
- Reduces predictability of overwhelming force

**Option 4: Skill-Based Luck**
- Players can research or build structures that improve luck range
- High-level players might have luck range of -10% to +30%
- Rewards investment in luck-improving technologies
- Adds strategic depth to luck system

**Option 5: Conditional Luck**
- Luck range varies based on battle circumstances
- Close battles have higher luck variance
- Overwhelming victories have minimal luck impact
- Adds drama to competitive battles

**Luck Tuning Parameters:**

- **Luck Range**: How much variance is possible (±10%, ±25%, ±50%)
- **Luck Distribution**: Uniform, gaussian, or biased
- **Luck Scope**: Per-battle, per-unit-type, or per-combat-round
- **Luck Visibility**: Do players see luck values in reports?
- **Luck Frequency**: Every battle, or only certain battle types

**Luck Impact on Gameplay:**

- Prevents perfect battle simulation and planning
- Adds excitement and unpredictability
- Helps underdogs occasionally win
- Can frustrate players who lose due to bad luck
- Reduces skill ceiling (luck can override superior strategy)



### Combined Morale and Luck

Many games use both systems together for maximum balance and variance.

**Combined System Benefits:**

- Morale provides systematic protection for weaker players
- Luck adds unpredictability to all battles
- Together they create dynamic, interesting combat
- Prevents stagnation and perfect predictability

**Tuning Recommendations:**

- Start with moderate morale (minimum 50%) and moderate luck (±15%)
- Monitor player feedback and battle statistics
- Adjust based on desired game balance and competitiveness
- Consider different settings for different game phases (early, mid, late game)
- Allow server-specific customization for different player preferences

---

## Battle Reports

Battle reports are the primary way players learn about combat outcomes. Reports must be informative, clear, and engaging.

### Report Structure

Every battle report contains:

**Header Information:**
- Battle timestamp (exact date and time)
- Attacker name and village
- Defender name and village
- Attack type (normal, raid, siege, scout, etc.)
- Battle outcome (attacker victory, defender victory, close call)
- Luck and morale values (if applicable)

**Troop Information:**
- Attacker's troops sent (by unit type)
- Attacker's troops lost (by unit type)
- Attacker's troops survived (by unit type)

- Defender's troops present (by unit type, including supporters)
- Defender's troops lost (by unit type)
- Defender's troops survived (by unit type)

**Battle Details:**
- Wall level before and after battle
- Terrain and weather modifiers
- Time of day effects
- Special unit actions (nobles, heroes, etc.)

**Outcome Information:**
- Resources plundered (if attacker won)
- Loyalty change (if noble attack)
- Building damage (if applicable)
- Experience gained (if applicable)

**Supporter Information:**
- List of all players who supported defender
- Each supporter's troop losses
- Supporter reports sent to each supporting player

### Example Battle Reports

#### Example 1: Crushing Attacker Victory

```
=== BATTLE REPORT ===
Time: December 1, 2025 14:32:47
Attacker: WarLord (Village: Ironforge)
Defender: Farmer123 (Village: Peaceful Valley)
Type: Normal Attack

OUTCOME: Decisive Victory for Attacker
Luck: Attacker +12%, Defender -8%
Morale: 100%

ATTACKER FORCES:
Sent: 500 Swordsmen, 300 Archers, 200 Cavalry, 50 Catapults
Lost: 45 Swordsmen, 20 Archers, 10 Cavalry, 2 Catapults
Survived: 455 Swordsmen, 280 Archers, 190 Cavalry, 48 Catapults

DEFENDER FORCES:

Present: 80 Spearmen, 40 Archers, 20 Cavalry
Lost: 80 Spearmen, 40 Archers, 20 Cavalry (ALL DESTROYED)
Survived: None

BATTLE DETAILS:
Wall Level: 15 → 13 (damaged by catapults)
Terrain: Plains (no modifier)
Time: Afternoon (no modifier)

PLUNDER:
Wood: 12,450
Clay: 8,320
Iron: 6,180
Gold: 2,500
Total Haul: 29,450 resources

Your troops are returning home with the plunder.
Estimated arrival: December 1, 2025 16:15:22

The enemy village lies in ruins. Your warriors return victorious!
```

#### Example 2: Narrow Attacker Victory

```
=== BATTLE REPORT ===
Time: December 1, 2025 09:15:33
Attacker: SneakyPete (Village: Shadow Hills)
Defender: StrongDefender (Village: Fortress Prime)
Type: Raid

OUTCOME: Narrow Victory for Attacker
Luck: Attacker -5%, Defender +3%
Morale: 85%

ATTACKER FORCES:
Sent: 300 Light Cavalry, 200 Mounted Archers
Lost: 185 Light Cavalry, 120 Mounted Archers
Survived: 115 Light Cavalry, 80 Mounted Archers

DEFENDER FORCES:
Present: 200 Spearmen, 100 Swordsmen, 50 Archers

Lost: 200 Spearmen, 100 Swordsmen, 50 Archers (ALL DESTROYED)
Survived: None

BATTLE DETAILS:
Wall Level: 18 (no change - raid doesn't damage walls)
Terrain: Forest (+10% defender bonus)
Time: Dawn (no modifier)

PLUNDER:
Wood: 3,200
Clay: 2,800
Iron: 1,900
Gold: 450
Total Haul: 8,350 resources (limited by carrying capacity)

Your raiders barely escaped with their lives, but they bring home plunder!
The battle was fierce - you lost over 60% of your forces.
```

#### Example 3: Narrow Defender Victory

```
=== BATTLE REPORT ===
Time: December 1, 2025 22:48:12
Attacker: Aggressor99 (Village: War Camp)
Defender: LuckyDefender (Village: Last Stand)
Type: Normal Attack

OUTCOME: Narrow Victory for Defender
Luck: Attacker -18%, Defender +22%
Morale: 100%

ATTACKER FORCES:
Sent: 400 Swordsmen, 250 Archers, 150 Cavalry, 30 Rams
Lost: 400 Swordsmen, 250 Archers, 150 Cavalry, 30 Rams (ALL DESTROYED)
Survived: None

DEFENDER FORCES:
Present: 180 Spearmen, 120 Swordsmen, 80 Archers, 40 Cavalry

Lost: 165 Spearmen, 105 Swordsmen, 72 Archers, 35 Cavalry
Survived: 15 Spearmen, 15 Swordsmen, 8 Archers, 5 Cavalry

BATTLE DETAILS:
Wall Level: 20 → 18 (damaged by rams before battle)
Terrain: Hills (+15% defender bonus)
Time: Night (-10% attacker penalty)

PLUNDER: None (defender victory)

Your village held! The enemy was completely destroyed!
Only a handful of your brave defenders survived the onslaught.
Reinforcements are urgently needed!
```

#### Example 4: Devastating Defender Victory

```
=== BATTLE REPORT ===
Time: December 1, 2025 16:20:05
Attacker: Newbie42 (Village: Starter Town)
Defender: Veteran (Village: Stronghold)
Type: Normal Attack

OUTCOME: Crushing Victory for Defender
Luck: Attacker -3%, Defender +7%
Morale: 55% (attacking much stronger player)

ATTACKER FORCES:
Sent: 100 Spearmen, 50 Swordsmen, 25 Archers
Lost: 100 Spearmen, 50 Swordsmen, 25 Archers (ALL DESTROYED)
Survived: None

DEFENDER FORCES:
Present: 500 Swordsmen, 300 Archers, 200 Cavalry, 100 Heavy Infantry
Lost: 8 Swordsmen, 3 Archers
Survived: 492 Swordsmen, 297 Archers, 200 Cavalry, 100 Heavy Infantry

BATTLE DETAILS:
Wall Level: 20 (no change)
Terrain: Plains (no modifier)
Time: Afternoon (no modifier)


PLUNDER: None (defender victory)

Your attack was utterly destroyed! The enemy barely noticed your presence.
Your troops' morale was low due to attacking a much stronger opponent.
Perhaps reconsider your target selection...
```

#### Example 5: Successful Scout

```
=== SCOUT REPORT ===
Time: December 1, 2025 11:30:22
Attacker: SpyMaster (Village: Watchtower)
Defender: Target (Village: Rich Village)
Type: Scouting Mission

OUTCOME: Successful Reconnaissance
Your scouts infiltrated undetected!

DEFENDER FORCES:
Spearmen: 45
Swordsmen: 30
Archers: 25
Light Cavalry: 10
Heavy Cavalry: 5
Scouts: 8 (killed your scouts would have been detected)

RESOURCES:
Wood: 28,450
Clay: 32,100
Iron: 18,900
Gold: 5,200

BUILDINGS:
Headquarters: Level 15
Barracks: Level 18
Stable: Level 12
Workshop: Level 8
Wall: Level 16
Warehouse: Level 20
Hiding Place: Level 5 (protects ~2,000 of each resource)

SCOUT CASUALTIES:
Sent: 50 Scouts
Lost: 0 Scouts
Survived: 50 Scouts

The village appears lightly defended with substantial resources!
An excellent target for a raid.
```



#### Example 6: Partial Scout (Some Scouts Killed)

```
=== SCOUT REPORT ===
Time: December 1, 2025 13:45:18
Attacker: Curious (Village: Lookout Point)
Defender: Vigilant (Village: Alert Base)
Type: Scouting Mission

OUTCOME: Partial Intelligence Gathered
Some scouts were detected and killed!

DEFENDER FORCES:
Spearmen: ~80-120 (estimate)
Swordsmen: ~50-80 (estimate)
Archers: Unknown
Cavalry: Present (exact count unknown)
Scouts: 40+ (strong counter-scouting presence)

RESOURCES:
Wood: ~15,000-25,000 (estimate)
Clay: ~20,000-30,000 (estimate)
Iron: Unknown
Gold: Unknown

BUILDINGS:
Headquarters: Level 12-15 (estimate)
Barracks: High level (active training)
Wall: Level 14+ (strong fortification)
Other buildings: Information incomplete

SCOUT CASUALTIES:
Sent: 50 Scouts
Lost: 32 Scouts (killed by defender's scouts)
Survived: 18 Scouts

The village has strong counter-intelligence. Information is incomplete.
Defender is now aware of your scouting attempt.
```

#### Example 7: Failed Scout

```
=== SCOUT REPORT ===
Time: December 1, 2025 19:12:44
Attacker: BadSpy (Village: Blind Eye)
Defender: Paranoid (Village: Secure Fortress)
Type: Scouting Mission

OUTCOME: Mission Failed
All scouts were detected and eliminated!


DEFENDER FORCES: Unknown
RESOURCES: Unknown
BUILDINGS: Unknown

SCOUT CASUALTIES:
Sent: 30 Scouts
Lost: 30 Scouts (ALL KILLED)
Survived: 0 Scouts

Your scouts were intercepted before gathering any intelligence.
The defender has been alerted to your interest in their village.
Consider sending more scouts or attacking blind.
```

#### Example 8: Noble Attack with Loyalty Reduction

```
=== BATTLE REPORT ===
Time: December 1, 2025 03:22:15
Attacker: Conqueror (Village: Empire Capital)
Defender: Victim (Village: Doomed Village)
Type: Noble Attack (Conquest Attempt)

OUTCOME: Victory for Attacker - Loyalty Reduced!
Luck: Attacker +5%, Defender -2%
Morale: 100%

ATTACKER FORCES:
Sent: 1 Noble, 100 Swordsmen (escort), 50 Cavalry (escort)
Lost: 35 Swordsmen, 18 Cavalry
Survived: 1 Noble (SURVIVED!), 65 Swordsmen, 32 Cavalry

DEFENDER FORCES:
Present: 40 Spearmen, 25 Swordsmen, 15 Archers
Lost: 40 Spearmen, 25 Swordsmen, 15 Archers (ALL DESTROYED)
Survived: None

BATTLE DETAILS:
Wall Level: 8 (previously damaged)
Terrain: Plains (no modifier)
Time: Night (no modifier)

LOYALTY IMPACT:
Previous Loyalty: 52
Loyalty Reduced By: 25
New Loyalty: 27


PLUNDER:
Wood: 1,200
Clay: 980
Iron: 650
Gold: 150

Your noble survived and reduced the village's loyalty!
One or two more successful noble attacks will conquer this village.
Continue the assault before they can rebuild defenses!
```

#### Example 9: Failed Noble Attack (Noble Killed)

```
=== BATTLE REPORT ===
Time: December 1, 2025 12:08:37
Attacker: Overconfident (Village: Hubris)
Defender: Prepared (Village: Ready Village)
Type: Noble Attack (Conquest Attempt)

OUTCOME: Devastating Defeat for Attacker
Luck: Attacker -12%, Defender +15%
Morale: 100%

ATTACKER FORCES:
Sent: 1 Noble, 50 Swordsmen (escort), 30 Cavalry (escort)
Lost: 1 Noble (KILLED!), 50 Swordsmen, 30 Cavalry (ALL DESTROYED)
Survived: None

DEFENDER FORCES:
Present: 150 Spearmen, 80 Swordsmen, 60 Archers
Supporting: TribeMate1 (100 Swordsmen), TribeMate2 (80 Spearmen)
Lost: 45 Spearmen, 22 Swordsmen, 18 Archers, 28 Supported Swordsmen, 15 Supported Spearmen
Survived: 105 Spearmen, 58 Swordsmen, 42 Archers, plus most supported troops

BATTLE DETAILS:
Wall Level: 18 (strong fortification)
Terrain: Hills (+15% defender bonus)
Time: Afternoon (no modifier)

LOYALTY IMPACT: None (noble was killed before reducing loyalty)


PLUNDER: None (defender victory)

DISASTER! Your noble was killed in battle!
The conquest attempt has failed catastrophically.
You must train a new noble (very expensive) to continue conquest efforts.
The defender was much better prepared than expected.
```

#### Example 10: Mutual Destruction

```
=== BATTLE REPORT ===
Time: December 1, 2025 17:33:29
Attacker: Desperate (Village: Last Hope)
Defender: Stubborn (Village: Never Surrender)
Type: Normal Attack

OUTCOME: Mutual Destruction (Counts as Defender Victory)
Luck: Attacker +2%, Defender -1%
Morale: 100%

ATTACKER FORCES:
Sent: 250 Swordsmen, 150 Archers, 100 Cavalry
Lost: 250 Swordsmen, 150 Archers, 100 Cavalry (ALL DESTROYED)
Survived: None

DEFENDER FORCES:
Present: 200 Spearmen, 120 Swordsmen, 80 Archers
Lost: 200 Spearmen, 120 Swordsmen, 80 Archers (ALL DESTROYED)
Survived: None

BATTLE DETAILS:
Wall Level: 12 → 10 (damaged)
Terrain: Plains (no modifier)
Time: Evening (no modifier)

PLUNDER: None (no survivors to carry resources)

Both armies were completely annihilated!
The battlefield is littered with the dead from both sides.
Neither attacker nor defender achieved their objectives.
The village stands empty and vulnerable...
```



### Supporter Battle Reports

When a player supports another player's village, they receive separate reports about their troops' performance.

**Supporter Report Example:**

```
=== SUPPORT BATTLE REPORT ===
Time: December 1, 2025 14:32:47
Your Support: From Village "Defender Base" to Ally "Farmer123" Village "Peaceful Valley"
Attacker: WarLord (Village: Ironforge)
Type: Normal Attack

OUTCOME: Defeat - Your supported troops were destroyed
Luck: Attacker +12%, Defender -8%

YOUR SUPPORTED FORCES:
Present: 80 Swordsmen, 50 Archers, 30 Cavalry
Lost: 80 Swordsmen, 50 Archers, 30 Cavalry (ALL DESTROYED)
Survived: None

TOTAL DEFENDER FORCES:
Your Troops: 80 Swordsmen, 50 Archers, 30 Cavalry
Ally's Troops: 80 Spearmen, 40 Archers, 20 Cavalry
Other Support: None

BATTLE OUTCOME:
All defending forces were eliminated.
The attacker plundered 29,450 resources from your ally's village.

Your support was not enough to save the village.
Consider sending more troops or coordinating with other tribe members.
```

---

## Edge Cases and Special Scenarios

### Simultaneous Attacks

When multiple attacks arrive at the exact same server tick:

**Resolution Order:**

- All attacks arriving in same tick are processed in order of attack ID (when they were launched)
- First attack fights against full defenses
- Second attack fights against survivors of first battle
- Third attack fights against survivors of second battle, etc.

- Each attack is fully resolved before next attack begins

**Strategic Implications:**

- First attack faces strongest defenses but weakens them for follow-ups
- Later attacks face weaker defenses but may find no resources left
- Coordination requires precise timing to hit same second
- Defender must survive all attacks in sequence

**Alternative: True Simultaneous Resolution**

Some game variants resolve truly simultaneous attacks:

- All attacks arriving in same tick combine their forces
- All attackers fight together against defenders
- Casualties and plunder are distributed proportionally among attackers
- More realistic but more complex to implement and explain

### Multiple Defenders (Support Stacking)

When multiple players support the same village:

**Combined Defense:**

- All supported troops fight together as one defensive force
- Wall bonus applies to all defenders equally
- Casualties are distributed across all defending forces
- Each supporter receives individual report about their troops

**Casualty Distribution:**

- Casualties are proportional to each player's contribution
- If Player A has 60% of defenders, they take ~60% of casualties
- Random variance means exact proportions vary
- Weaker unit types die first regardless of owner



**Support Coordination Challenges:**

- Supporters may recall troops without warning
- Supporters may not respond to urgent requests
- Supporters' troops consume host's food resources
- Trust issues if supporters are unreliable

### Offline Defenders

When defender is offline during attack:

**No Special Protection:**

- Offline players receive no special bonuses or protections
- Attacks resolve normally whether defender is online or offline
- Battle reports are waiting when defender logs back in
- This encourages 24/7 play or village sitting arrangements

**Optional Offline Protection Mechanics:**

Some games offer limited protection for offline players:

**Option 1: Beginner Protection**
- New players (first 3-7 days) cannot be attacked while offline
- Protection ends when they attack someone or reach certain points
- Prevents new players from being farmed before they understand game

**Option 2: Vacation Mode**
- Players can activate vacation mode when going away
- While in vacation mode, cannot be attacked
- Cannot attack others or build/train while in vacation mode
- Minimum duration (e.g., 2 days) prevents abuse
- Cooldown period before can activate again

**Option 3: Offline Shield**
- Players get limited shield time (e.g., 8 hours per day)
- Can activate shield when logging off
- Shield prevents attacks but also prevents player actions
- Regenerates slowly over time



### Emergency Shields and Protection

**Post-Conquest Protection:**

- When village is conquered, new owner gets temporary protection (e.g., 12 hours)
- Prevents immediate re-conquest before new owner can stabilize
- Protection only applies to that specific village
- New owner's other villages remain vulnerable

**Beginner Protection:**

- New players cannot be attacked for first 3-7 days
- Protection ends early if player attacks someone
- Protection ends if player reaches certain point threshold
- Prevents experienced players from farming complete beginners

**Tribe Protection:**

- Some games prevent tribe members from attacking each other
- Prevents internal tribe warfare and griefing
- May be toggleable by tribe leadership
- Diplomatic implications if tribes want to merge or split

### Server Lag and Timing Issues

**Lag Compensation:**

- Server may experience lag during peak times or under heavy load
- Attacks may process slightly late if server is overloaded
- Game should use server timestamp, not client timestamp, for all timing
- Players should be warned if server is experiencing unusual lag

**Timing Precision:**

- All timing should be precise to the second
- Display exact arrival times (HH:MM:SS format)
- Allow players to calculate precise timing for coordination
- Server tick rate determines minimum timing precision



**Rollback Scenarios:**

- If server crashes during battle resolution, what happens?
- Option 1: Re-process all battles after server restart
- Option 2: Roll back to last stable state before crash
- Option 3: Mark battles as "unresolved" and allow manual resolution
- Must prevent duplication exploits or lost troops

### Cancelled Attacks

**Attack Cancellation Rules:**

- Attacks can be cancelled while troops are in transit
- Cancellation may have time limit (e.g., can only cancel in first 50% of journey)
- Cancelled troops return home immediately or continue to cancellation point
- No casualties from cancellation (unless variant has desertion mechanics)

**Fake Attack Cancellation:**

- Fakes are cancelled attacks that appear real to defender
- Defender sees attack until it's cancelled
- Creates uncertainty and forces defensive preparation
- Attacker wastes time but not troops

### Intercepted Attacks

Some game variants allow intercepting attacks in transit:

**Interception Mechanics:**

- Defender or ally sends troops to intercept attacking army
- Requires calculating intercept point between attacker's origin and target
- Battle occurs in open field (no wall bonus for either side)
- Winner continues to their destination; loser is eliminated

**Interception Challenges:**

- Extremely difficult to calculate precise intercept timing
- Requires knowing exact attack launch time and troop speed
- High risk: if timing is wrong, intercept fails completely
- Advanced tactic for experienced players



### Resource Hiding and Protection

**Hiding Place Mechanics:**

- Hiding place building protects portion of resources from plunder
- Higher level hiding place protects more resources
- Protected resources cannot be plundered even if attacker wins
- Encourages building hiding places as defensive investment

**Resource Evacuation:**

- Defender can send resources away before attack arrives
- Send resources to ally's village or own other village
- Resources are safe during transit and at destination
- Must time evacuation to avoid resources being present during attack
- Resources must eventually return, creating timing vulnerability

**Warehouse Overflow:**

- If resources exceed warehouse capacity, overflow is more vulnerable
- Some games make overflow resources easier to plunder
- Encourages upgrading warehouses or spending excess resources

### Tribe Warfare Mechanics

**Tribe vs Tribe Wars:**

- Formal war declarations between tribes
- During war, special rules may apply (no morale penalties, bonus points, etc.)
- War objectives: eliminate enemy tribe, conquer territory, accumulate points
- War duration: time-limited (e.g., 1 week) or until surrender
- War rewards: winning tribe gets bonuses, losing tribe may face penalties

**Tribe Coordination:**

- Tribe forums, chat, and planning tools
- Coordinated attack planning with multiple players
- Shared intelligence and scouting information
- Tribe-wide strategies and target prioritization



### Ghost Villages (Abandoned/Inactive Players)

**Inactive Player Villages:**

- Players who haven't logged in for extended period (e.g., 7+ days)
- Villages may become "abandoned" and easier to attack
- Reduced or no morale penalties for attacking inactive players
- May have reduced defenses or decaying buildings
- Encourages active play and provides targets for active players

**Village Deletion:**

- Extremely inactive players (e.g., 30+ days) may have villages deleted
- Deleted villages become barbarian villages (NPC controlled)
- Barbarian villages can be conquered by any player
- Prevents map clutter from permanently inactive players

### Barbarian Villages (NPC Villages)

**Barbarian Mechanics:**

- NPC-controlled villages scattered across map
- Have fixed defensive forces (don't train new troops)
- Have resources that regenerate over time
- Can be attacked and conquered by players
- Provide expansion opportunities without attacking other players
- Difficulty varies by location (edge villages easier, center villages harder)

**Barbarian Conquest:**

- Conquering barbarians follows same rules as player villages
- Must reduce loyalty to zero with noble attacks
- Conquered barbarian villages become player villages
- Good targets for new players to expand territory

### Special Events and Modifiers

**Seasonal Events:**

- Holiday events with special bonuses or challenges
- Example: "Winter War" event with snow terrain modifiers
- Example: "Harvest Festival" with increased resource plunder
- Limited-time events create variety and excitement



**World Wonders:**

- Late-game objectives that tribes compete to build and defend
- Require massive resource investment and coordination
- Attacking world wonders is extremely difficult
- First tribe to complete world wonder wins the game world
- Creates endgame focus and conclusion to server

### Battle Simulation and Planning Tools

**In-Game Simulator:**

- Tool that predicts battle outcomes based on input forces
- Helps players plan attacks and assess risks
- Shows estimated casualties and success probability
- May or may not account for luck variance
- Encourages strategic planning over blind attacking

**External Calculators:**

- Community-created tools for battle simulation
- More detailed than in-game tools
- May include advanced features (optimal unit composition, timing calculators)
- Part of meta-game and community engagement

### Anti-Cheating Measures

**Multi-Accounting Prevention:**

- Detect and ban players controlling multiple accounts
- Prevent self-supporting or self-farming
- IP tracking, behavior analysis, timing patterns
- Community reporting of suspected multi-accounters

**Bot Detection:**

- Detect automated scripts or bots
- Unusual timing precision or inhuman reaction speeds
- Captcha challenges for suspicious activity
- Ban players using automation tools

**Exploit Prevention:**

- Prevent resource duplication exploits
- Prevent timing exploits or server manipulation
- Regular security audits and penetration testing
- Quick response to discovered exploits



---

## Combat Balance Considerations

### Unit Balance

**Rock-Paper-Scissors Design:**

- Spearmen counter cavalry
- Cavalry counters archers
- Archers counter infantry
- Siege weapons counter walls
- No single unit type dominates all scenarios

**Cost-Effectiveness:**

- Expensive units should be proportionally more effective
- Cheap units should have niche uses
- Balance between training time, resource cost, and combat power
- Consider population space (expensive units take more space)

### Defender Advantage

**Why Defenders Should Have Advantage:**

- Encourages building defenses and infrastructure
- Prevents constant successful raiding (would make game frustrating)
- Rewards preparation and planning
- Makes conquest challenging and meaningful
- Walls and fortifications should matter

**Balancing Defender Advantage:**

- Too strong: no one can successfully attack, game stagnates
- Too weak: constant raiding, no one can build up, chaos
- Sweet spot: well-planned attacks succeed, poorly-planned attacks fail
- Defender advantage should scale with investment (wall level, etc.)

### Attacker Incentives

**Why Players Should Attack:**

- Resource gain from plunder
- Territory expansion through conquest
- Weakening competitors
- Tribal warfare objectives
- Ranking and prestige



**Making Attacks Worthwhile:**

- Plunder should exceed attack cost (for successful attacks)
- Conquest should provide long-term strategic value
- Attacking should be more profitable than pure farming/building
- Risk-reward balance: high risk attacks have high rewards

### New Player Protection

**Protecting Beginners:**

- Beginner protection period (3-7 days)
- Morale system penalizes attacking much weaker players
- Tutorial guidance on defense and survival
- Starter resources and troops
- Tribe recruitment and mentorship programs

**Graduation to Full Game:**

- Protection ends when player attacks someone
- Protection ends when player reaches point threshold
- Protection ends after time limit
- Gradual exposure to full game mechanics

### Endgame Balance

**Late Game Challenges:**

- Prevent dominant players/tribes from becoming unbeatable
- World wonders or victory conditions create endpoints
- Diminishing returns on expansion (harder to defend many villages)
- Coalition mechanics allow smaller tribes to unite against dominant tribe
- Server resets or new worlds provide fresh starts

---

## Implementation Priorities

### Phase 1: Core Combat (MVP)

- Basic attack and defense resolution
- Simple power calculation (attack vs defense)
- Troop casualties and death
- Resource plunder for attacker victories
- Basic battle reports
- Wall defensive bonus



### Phase 2: Attack Variety

- Multiple attack types (normal, raid, siege)
- Scouting missions
- Support movements
- Attack cancellation
- Fake attacks

### Phase 3: Advanced Mechanics

- Morale system
- Luck variance
- Terrain modifiers
- Time of day effects
- Unit-specific bonuses (cavalry vs archers, etc.)

### Phase 4: Conquest System

- Noble/chieftain units
- Loyalty mechanics
- Village conquest
- Post-conquest protection

### Phase 5: Polish and Balance

- Detailed battle reports with multiple examples
- Battle simulator tool
- Advanced tactics support (timing, coordination)
- Balance tuning based on player feedback
- Edge case handling

### Phase 6: Social and Tribal Features

- Tribe warfare mechanics
- Coordinated attack tools
- Shared intelligence
- Tribe vs tribe wars
- World wonders and victory conditions

---

## Testing and Balancing

### Combat Testing Scenarios

**Basic Combat Tests:**

- Equal forces should result in close battle
- 2x forces should win decisively
- 10x forces should win overwhelmingly
- Wall bonus should significantly help defender
- Morale should penalize attacking weaker players



**Unit Balance Tests:**

- Each unit type should have situations where it excels
- No unit should be universally best
- Cost should correlate with effectiveness
- Counter-units should provide significant advantage

**Edge Case Tests:**

- Simultaneous attacks resolve correctly
- Multiple supporters combine properly
- Cancelled attacks don't cause bugs
- Offline defenders are handled correctly
- Server lag doesn't break timing

**Exploit Testing:**

- Cannot duplicate resources through combat
- Cannot exploit timing to avoid casualties
- Cannot manipulate battle reports
- Cannot cheat with multiple accounts

### Balance Metrics to Monitor

**Attack Success Rate:**

- Overall: should be 40-60% (balanced)
- Too high: defenders too weak, game too chaotic
- Too low: attackers too weak, game too static

**Resource Flow:**

- Plunder should be significant part of economy
- But not so much that building is pointless
- Monitor total resources plundered vs produced

**Player Retention:**

- Are new players surviving and growing?
- Are players quitting due to being farmed?
- Are endgame players staying engaged?

**Tribal Activity:**

- Are tribes coordinating attacks?
- Are tribe wars happening?
- Is tribal cooperation meaningful?



### Tuning Parameters

Key parameters that can be adjusted for balance:

**Combat Parameters:**
- Base attack/defense values for each unit
- Wall defensive bonus multiplier
- Siege weapon wall damage
- Casualty distribution algorithm
- Luck range (±10%, ±25%, etc.)
- Morale minimum and curve

**Economic Parameters:**
- Plunder carrying capacity
- Resource hiding protection
- Plunder priority order
- Building damage rates

**Timing Parameters:**
- Unit movement speeds
- Attack visibility timing
- Support arrival requirements
- Noble loyalty reduction amount

**Protection Parameters:**
- Beginner protection duration
- Offline protection (if any)
- Post-conquest protection duration
- Morale penalties for attacking weak players

---

## Future Enhancements

### Advanced Combat Features

**Hero Units:**
- Special unique units with special abilities
- Level up through combat experience
- Can turn tide of close battles
- Provide strategic depth and personalization

**Formations and Tactics:**
- Choose battle formations (defensive, aggressive, balanced)
- Formations affect combat outcome
- Adds strategic layer beyond just unit counts

**Weather System:**
- Dynamic weather affects battles
- Rain slows cavalry, fog helps attackers, etc.
- Adds unpredictability and realism



**Terrain Variety:**
- Different terrain types with unique effects
- Mountains, rivers, forests, swamps, etc.
- Terrain affects movement and combat differently
- Strategic value of different locations

**Naval Combat:**
- Ships and naval units
- Island maps with water barriers
- Naval invasions and transport ships
- Expands strategic possibilities

**Siege Warfare:**
- Extended sieges lasting hours or days
- Siege camps outside enemy villages
- Supply lines and attrition
- More realistic medieval warfare

**Espionage and Sabotage:**
- Spy units that gather intelligence
- Saboteurs that damage buildings or kill troops
- Counter-intelligence to catch spies
- Adds covert operations layer

**Mercenaries and Allies:**
- Hire NPC mercenary troops
- Temporary alliances with other players
- Mercenaries cost gold but available immediately
- Provides flexibility for players without large armies

**Battle Replays:**
- Visual replay of battles
- See how battle unfolded round by round
- Educational for learning combat mechanics
- Engaging and immersive

**Achievements and Titles:**
- Combat achievements (100 victories, conquered 10 villages, etc.)
- Titles based on combat prowess
- Leaderboards for various combat metrics
- Provides goals and recognition



### Quality of Life Features

**Attack Templates:**
- Save common attack compositions
- Quick-send attacks with saved templates
- Reduces repetitive clicking

**Mass Operations:**
- Send same attack from multiple villages
- Coordinate timing across villages
- Bulk support sending

**Attack Planner:**
- Visual timeline of planned attacks
- Coordinate complex multi-wave operations
- See all incoming and outgoing attacks at once

**Mobile Notifications:**
- Push notifications for incoming attacks
- Alerts when battles resolve
- Allows defensive response even when away from computer

**Battle Statistics:**
- Personal combat statistics dashboard
- Win/loss ratio, total kills, resources plundered
- Compare with other players
- Track improvement over time

---

## Conclusion

The combat system is the most complex and important part of the game. It must be:

- **Fair**: Skill and preparation should determine outcomes, not just luck or time investment
- **Engaging**: Combat should be exciting, strategic, and rewarding
- **Balanced**: No dominant strategies; multiple viable approaches
- **Accessible**: New players can understand basics quickly
- **Deep**: Advanced players can master complex tactics
- **Social**: Encourages cooperation and tribal warfare
- **Performant**: Server can handle thousands of simultaneous battles

Start with core mechanics (Phase 1-2), test thoroughly, gather player feedback, and iterate. Add advanced features (Phase 3-6) based on player demand and game balance needs.



The combat system should evolve with the game. Monitor metrics, listen to community feedback, and continuously refine balance. A well-designed combat system will keep players engaged for months or years, creating memorable battles and epic tribal wars that players will talk about long after the game world ends.

---

## Quick Reference Tables

### Attack Type Comparison

| Attack Type | Speed | Primary Goal | Wall Damage | Plunder | Visibility | Risk Level |
|-------------|-------|--------------|-------------|---------|------------|------------|
| Normal Attack | 100% | Kill troops, plunder | Moderate | Standard | High | Medium |
| Raid | 150% | Fast plunder | None | +25% capacity | Medium | Low |
| Siege | 75% | Destroy walls, conquer | High | Low priority | Very High | High |
| Scout | 200% | Intelligence | None | None | Low | Very Low |
| Fake | 100% | Deception | None | None | High | None |
| Noble | 80% | Conquest | None | Low | Very High | Very High |
| Support | 100% | Defend ally | N/A | N/A | Low | Varies |

### Morale System Options Summary

| Option | Basis | Pros | Cons | Recommended For |
|--------|-------|------|------|-----------------|
| Village Points | Single village strength | Simple, intuitive | Doesn't account for player's other villages | Casual servers |
| Player Points | Total player strength | Fair, prevents farming | More complex | Competitive servers |
| Battle History | Recent wins/losses | Dynamic, rewards success | Can snowball | Experimental servers |
| Distance | Geographic proximity | Encourages local warfare | May feel arbitrary | Roleplay servers |
| Hybrid | Multiple factors | Most realistic | Most complex | Hardcore servers |



### Battle Outcome Scenarios

| Scenario | Attacker Result | Defender Result | Resources | Loyalty | Typical Cause |
|----------|----------------|-----------------|-----------|---------|---------------|
| Crushing Attacker Win | <20% casualties | 100% casualties | Full plunder | Reduced if noble | Overwhelming force |
| Narrow Attacker Win | 50-80% casualties | 100% casualties | Partial plunder | Reduced if noble | Close battle |
| Narrow Defender Win | 100% casualties | 80-95% casualties | None | No change | Close battle, good luck |
| Crushing Defender Win | 100% casualties | <20% casualties | None | No change | Strong defense, poor attack |
| Mutual Destruction | 100% casualties | 100% casualties | None | No change | Perfectly matched forces |
| Failed Scout | All scouts killed | No casualties | None | No change | Strong counter-scouts |
| Successful Scout | No casualties | No casualties | Intelligence gained | No change | Weak/no counter-scouts |

### Recommended Starting Values

| Parameter | Recommended Value | Rationale |
|-----------|------------------|-----------|
| Luck Range | ±15% | Enough variance to matter, not enough to dominate |
| Minimum Morale | 50% | Protects weak players without eliminating attacks |
| Wall Bonus (Max) | +200% defense | Makes walls valuable without being impenetrable |
| Beginner Protection | 5 days | Long enough to learn, short enough to stay engaged |
| Scout Speed | 2x normal | Fast enough for intelligence gathering |
| Raid Speed | 1.5x normal | Faster than normal but not as fast as scouts |
| Noble Loyalty Reduction | 25 points | Requires 4 nobles to conquer (100 loyalty) |
| Plunder Capacity | 50% of troop count | Balanced between profit and military strength |

