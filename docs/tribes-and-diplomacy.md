# Tribes (Alliances) & Diplomacy System

## Overview

The Tribes system forms the social backbone of the game, enabling players to band together, coordinate military operations, share resources, and engage in complex diplomatic relationships. Tribes (also called alliances or clans) provide structure, identity, and collective power in a competitive medieval world.

---

## Tribe Structure

### Tribe Creation

- Any player with sufficient prestige (e.g., 500+ points) can found a tribe
- Creation cost: resources (wood, clay, iron) + gold
- Founder must choose:
  - Tribe name (3-30 characters, unique)
  - Tribe tag (2-5 characters, displayed in brackets)
  - Primary color scheme for banners and map markers
  - Tribe description (public-facing)
  - Recruitment policy (open, application-only, invite-only, closed)

### Size & Scaling

- Minimum members: 3 (to maintain active status)
- Maximum members: scales with tribe level
  - Level 1: 10 members
  - Level 5: 25 members
  - Level 10: 50 members
  - Level 15: 75 members
  - Level 20: 100 members
  - Level 25+: 150 members
- Inactive tribes (< 3 active members for 14 days) are automatically disbanded
- Members can leave freely (24-hour cooldown before joining another tribe)
- Kicked members have 72-hour cooldown before rejoining any tribe

### Tribe Leveling & Progression

Tribes gain experience through collective member activities:

- Member village conquests: 100 XP per village
- Successful defenses: 50 XP per major attack repelled
- Tribe quest completion: 200-1000 XP
- War victories: 500-5000 XP depending on opponent strength
- Member achievements: 10-50 XP per achievement
- Resource donations to tribe storage: 1 XP per 1000 resources

---

## Diplomacy States

- **Neutral**: Attacks allowed; no intel sharing; standard trade tax.
- **Non-Aggression Pact (NAP)**: Attacks warn but are not blocked; share basic intel (village list/points/status); map tint blue; breaking causes reputation loss and 24h treaty lockout.
- **Alliance**: Attacks blocked by default; support allowed with -20% travel time; reduced market tax; full intel sharing (reports + optional live movements); breaking causes major reputation loss and 48h lockout.
- **War**: Attacks free, support blocked; war feed/stats enabled; no trading (or heavy tax); map markers red.
- **Armistice/Truce**: Temporary ceasefire; attacks blocked; support allowed if agreed; auto-expires to Neutral unless upgraded.

**State Rules**
- One primary state per tribe pair; minimum durations: NAP 7d, Alliance 14d, War 24h, Truce 12h.
- Cooldowns: cannot re-ally within 48h after breaking; cannot declare war twice on same tribe within 24h.
- Mutual consent needed for NAP/Alliance/Truce; War is unilateral with warning timer.

---

## War Declaration & Resolution

**Declaration Flow**
1) Diplomat/Leader selects target, sets reason and start timer (default T+12h).
2) Broadcast to both tribes and world feed; map highlights pending war.
3) Prep window: attacks allowed but don’t count for war stats; support still allowed.
4) Start time: war active, support blocked, stats begin tracking.

**Victory Options (configurable)**
- Points: first to X war points (kills, village captures, objectives).
- Territory: control Y% of contested sectors for Z hours.
- Attrition: maintain kill/loss ratio above threshold for N days.
- Surrender: either side can offer terms; other must accept to end.

**War Points & Tracking**
- Scoring: 1 point per 1,000 enemy pop killed; 25 per village captured; 5 per successful defense with 3:1 casualty ratio.
- Objectives: map points grant periodic ticks while held; require 10m majority presence.
- Decay: war points decay 2% per day after day 7 (captures don’t decay).
- Anti-abuse: no points from alt/allied tags; repeated village swaps within 48h give zero points.
- UI: war dashboard with totals, 24h trend, member contributions; exports to war summary.

**Resolution**
- Auto-resolves after 30 days of inactivity or upon victory condition.
- Peace cooldown: enforced 24h Truce after war ends.
- War summary archived (losses, captures, MVPs, timeline).
- Reputation shifts: declaring on much smaller tribes reduces rep; beating stronger tribes grants prestige.

---

## Reputation & Betrayal

- Reputation score shown on tribe profile; affected by honoring/breaking treaties, fair or lopsided wars.
- Negative actions: breaking NAP/Alliance early, friendly fire, farming much weaker tribes repeatedly.
- Positive actions: honoring full durations, accepting surrenders, defending allies, ending wars cleanly.
- Threshold effects: Very Low = higher market tax, slower treaty approvals, warning badge; High = discounted diplomacy costs, faster approvals, cosmetic laurels.
- Public log of all treaty changes with timestamps, actors, and reputation deltas.

---

## Treaty Management UX & Safeguards

- **Proposal Flow**: Leaders/diplomats can propose NAP/Alliance/Truce; target tribe must accept; proposals auto-expire after 24h. Declines/expirations are logged and notify both sides.
- **Costs & Currency**: Proposals consume tribe diplomacy points (low for NAP/Truce, higher for Alliance). Optional gold surcharge enables instant activation; otherwise a 1-hour processing window where either side may cancel.
- **Rate Limits**: Max 3 outgoing proposals per tribe per 24h; repeated toggling with the same tribe inside 48h blocked. Minimum duration timers shown before confirmation.
- **Attack Warnings**: Attacking a treaty partner triggers a modal with the reputation hit and cooldown reminder; override requires leadership rank and is logged.
- **Visibility**: Members see current state and timers; leadership sees proposal status, exact end times, and who initiated changes. History tab lists created/accepted/broken/expired treaties with actor and reputation delta.

Level benefits:
- Increased member capacity
- Unlock tribe features (forums, shared storage, etc.)
- Unlock tribe skills and bonuses
- Prestige and ranking visibility
- Access to higher-tier tribe quests
- Cosmetic unlocks (banners, colors, effects)

### Tribe Identity Features

#### Tribe Tag & Name
- Tag appears in brackets before player names: [TAG] PlayerName
- Can be changed once per 30 days (costs gold)
- Name changes cost significantly more and require founder approval

#### Banner & Heraldry System
- Custom banner designer with:
  - Background patterns (solid, stripes, quarters, chevrons, etc.)
  - Primary and secondary colors
  - Symbols/emblems (animals, weapons, geometric shapes)
  - Border styles
- Banners displayed on:
  - Tribe profile page
  - Member villages (optional flag)
  - Map markers for tribe territories
  - Battle reports
  - Leaderboards

#### Tribe Profile
- Public description (500 characters)
- Internal description (members only, 1000 characters)
- Founding date and founder name
- Current member count and level
- Total tribe points (sum of all member points)
- War statistics (wins, losses, ongoing)
- Diplomatic relationships display
- Recruitment status and requirements
- Links to external communication (Discord, forums, etc.)

#### Tribe Motto & Culture
- Short motto (50 characters) displayed on profile
- Tribe culture tags: Aggressive, Defensive, Trading, Roleplay, Casual, Hardcore, etc.
- Preferred playstyle indicators
- Language/timezone information
- Activity level expectations
