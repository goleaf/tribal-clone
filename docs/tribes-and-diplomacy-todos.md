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

---

## Roles & Permissions

### Rank Hierarchy

Tribes use a flexible rank system with predefined roles and customizable permissions.

#### 1. Founder
- The original creator of the tribe
- Cannot be removed or demoted
- Can transfer founder status to another member (irreversible)
- Full access to all permissions
- Can disband the tribe

Permissions:
- All permissions enabled
- Disband tribe
- Transfer founder status
- Modify all tribe settings
- Manage all ranks and members

#### 2. Co-Leader / Duke
- Second-in-command, typically 1-3 per tribe
- Can perform most administrative functions
- Cannot disband tribe or remove founder

Permissions:
- Invite/kick members (except founder)
- Promote/demote members (up to their own rank)
- Manage diplomacy (declare war, ally, NAP)
- Edit tribe profile and settings
- Manage tribe storage
- Create/edit announcements
- Manage forums and pins
- View all member notes
- Access tribe treasury
- Assign tribe quests

Responsibilities:
- Overall tribe strategy and direction
- Conflict resolution
- Major diplomatic decisions
- Succession planning

#### 3. War Officer / General
- Military leadership role
- Focuses on combat coordination and strategy

Permissions:
- Create and manage war plans
- Assign attack targets
- Schedule operations
- Send mass military alerts
- View all battle reports (if shared)
- Manage war room and target lists
- Edit military announcements
- Access war statistics

Responsibilities:
- Coordinate offensive operations
- Plan defensive strategies
- Analyze enemy tribes
- Train members in combat tactics
- Manage war declarations (with approval)

#### 4. Diplomat / Ambassador
- Handles external relations and negotiations

Permissions:
- Propose diplomatic status changes
- Send diplomatic messages to other tribes
- View diplomatic history
- Create non-aggression pacts
- Negotiate treaties
- Access diplomacy logs
- Represent tribe in inter-tribe discussions

Responsibilities:
- Maintain relationships with allies
- Negotiate peace treaties
- Recruit allied tribes
- Handle diplomatic incidents
- Monitor political landscape

---

## Implementation Progress
- Added `TribeDiplomacyManager` (PHP) to persist tribe-to-tribe states (neutral, NAP, alliance, war, truce), handle unilateral war declarations with a 12h prep timer and 24h redeclare cooldown, enforce minimum durations per state, and convert wars into enforced truces.
- Added `WarDeclarationService` to publish war start/end events, apply war scoring hooks, and drive war dashboard population.
- Added reputation hooks on treaty changes (break/ally/peace) with configurable thresholds; reputation now surfaces on tribe profile.
- Added API endpoint `POST /api/tribes/{id}/treaties` for proposing treaties with validation (state cooldowns, power disparity guard), plus audit logging of actor and costs.

#### 5. Recruiter / Scout
- Manages member recruitment and onboarding

Permissions:
- Invite new members
- Accept/reject applications
- Send recruitment messages
- View applicant information
- Promote recruits to members
- Access recruitment statistics
- Edit recruitment requirements

Responsibilities:
- Find and invite quality players
- Screen applications
- Onboard new members
- Maintain recruitment standards
- Track recruitment success rates

#### 6. Logistics Officer / Quartermaster
- Manages tribe resources and support

Permissions:
- Manage tribe storage
- Distribute resources to members
- Track resource donations
- Create supply requests
- Organize resource convoys
- View member resource levels (if shared)
- Manage tribe treasury

Responsibilities:
- Coordinate resource sharing
- Support members under attack
- Organize building projects
- Manage tribe economy
- Track resource flows

#### 7. Intelligence Officer / Spy Master
- Gathers and analyzes information

Permissions:
- Access shared intelligence reports
- Create intel summaries
- Tag and categorize enemy information
- Manage spy network coordination
- View all scouting reports (if shared)
- Create threat assessments
- Access enemy tribe profiles

Responsibilities:
- Gather enemy intelligence
- Analyze threats
- Track enemy movements
- Coordinate espionage
- Brief leadership on threats

#### 8. Member / Warrior
- Standard tribe member with basic access

Permissions:
- View tribe forums
- Post in designated forum sections
- View announcements
- Participate in tribe chat
- Donate to tribe storage
- View tribe member list
- Share battle reports (optional)
- Participate in tribe quests

Responsibilities:
- Follow tribe rules and strategy
- Participate in coordinated attacks
- Support fellow members
- Contribute to tribe goals
- Maintain activity

#### 9. Recruit / Initiate
- New members on probation period

Permissions:
- View limited tribe information
- Read announcements
- Post in recruit forum section
- View basic member list
- Participate in tribe chat (may be restricted)

Responsibilities:
- Prove loyalty and activity
- Learn tribe culture
- Meet promotion requirements
- Follow orders
