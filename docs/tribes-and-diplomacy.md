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


### Custom Ranks

- Tribes can create custom ranks with specific permission sets
- Up to 10 custom ranks per tribe
- Each rank has a customizable name and color
- Permissions are granular and can be toggled individually
- Rank hierarchy determines who can manage whom

### Permission Categories

| Category | Permissions |
|----------|-------------|
| Members | Invite, Kick, Promote, Demote, View Notes, Edit Notes |
| Diplomacy | Declare War, Make Peace, Create Alliance, Create NAP, Break Treaties |
| Communication | Mass Mail, Announcements, Forum Moderation, Pin Messages |
| Resources | Access Storage, Distribute Resources, Manage Treasury, View Donations |
| Military | Create Operations, Assign Targets, View Reports, Manage War Room |
| Administration | Edit Profile, Change Settings, Manage Ranks, View Logs |
| Intelligence | View Intel, Edit Intel, Access Spy Reports, Create Assessments |

---

## Tribe Features

### Internal Forums

Multi-section forum system for tribe communication:

#### Forum Sections
- General Discussion (all members)
- Leadership Council (leaders only)
- War Room (military planning)
- Intelligence Reports (spy and scout data)
- Recruitment (applications and discussions)
- Off-Topic (social chat)
- Archives (old threads)


#### Forum Features
- Thread creation with titles and tags
- Reply and quote functionality
- Sticky/pinned threads
- Thread locking and archiving
- Polls and voting
- File attachments (images, battle reports)
- Search and filtering
- Notification system for replies
- Thread subscription
- Moderation tools (edit, delete, move)

### Announcements System

- Priority levels: Critical, High, Normal, Low
- Color-coded by priority
- Appear on tribe homepage
- Push notifications for critical announcements
- Expiration dates
- Read/unread tracking
- Acknowledgment requirement for critical announcements
- Archive of past announcements

### Member Notes & Profiles

#### Public Member Information
- Player name and rank
- Join date
- Total points and villages
- Activity status (online, offline, last seen)
- Tribe contributions
- Achievements and awards

#### Private Member Notes (Leadership Only)
- Performance notes
- Reliability ratings
- Disciplinary history
- Special skills or roles
- Contact information
- Timezone and availability
- Personal notes from officers


### Shared Intelligence System

Centralized knowledge base for tribe:

#### Intelligence Categories
- Enemy Tribe Profiles
  - Member lists and rankings
  - Known village locations
  - Military strength estimates
  - Diplomatic relationships
  - Activity patterns
  - Leadership structure
  
- Target Dossiers
  - Individual player analysis
  - Village defenses
  - Troop estimates
  - Resource levels
  - Attack history
  - Vulnerability windows

- Strategic Maps
  - Territory control overlays
  - Front line markers
  - Planned expansion zones
  - Enemy territory highlights
  - Allied positions

- Battle Report Library
  - Shared combat reports
  - Tagged and categorized
  - Analysis and lessons learned
  - Success/failure patterns

#### Intel Tools
- Tagging and categorization
- Search and filtering
- Contribution tracking (who added what)
- Verification status (confirmed, unconfirmed, outdated)
- Update timestamps
- Comments and discussions
- Export functionality


### Tribe-Wide Technologies

Collective research that benefits all members:

#### Military Technologies
- Coordinated Strike: +5% attack when multiple tribe members attack same target
- Rapid Response: -10% support troop travel time
- War Drums: +3% morale for all tribe members during declared wars
- Siege Mastery: +5% effectiveness for siege weapons
- Defensive Pact: +5% defense when supporting tribe members

#### Economic Technologies
- Trade Network: -5% marketplace fees between tribe members
- Shared Knowledge: +10% research speed for all members
- Resource Efficiency: -3% building costs
- Harvest Bonus: +5% resource production
- Storage Expansion: +10% warehouse capacity

#### Diplomatic Technologies
- Reputation: +10% relationship gain with other tribes
- Spy Network: +15% spy success rate
- Negotiator: -20% cost for diplomatic actions
- Intelligence Gathering: Reveal more information about enemies

#### Social Technologies
- Recruitment Boost: Attract higher quality applicants
- Member Retention: -50% cooldown for kicked members to rejoin
- Training Grounds: New members start with bonus resources
- Mentorship: Experienced members provide bonuses to nearby new players

Technology costs scale with tribe size and level. Research time: 3-14 days depending on tier.


### Shared Storage System

Tribe warehouse for collective resources:

#### Storage Features
- Capacity scales with tribe level
- Separate pools for wood, clay, iron, gold
- Donation tracking (who donated what and when)
- Withdrawal permissions by rank
- Withdrawal limits per rank
- Transaction history log
- Resource requests from members
- Automated distribution rules
- Emergency reserves (locked until crisis)

#### Resource Management
- Members donate voluntarily or via requirements
- Officers can distribute to members in need
- Support packages for members under attack
- Building project funding
- War chest for military operations
- New member starter packages

### Tribe Quests

Collective objectives that require tribe cooperation:

#### Quest Types
- Military Quests
  - Conquer X villages in 7 days
  - Win X battles against tribe Y
  - Defend against X attacks successfully
  - Destroy X enemy units
  
- Economic Quests
  - Collect X total resources
  - Build X buildings across tribe
  - Reach X total tribe points
  - Trade X resources with allies

- Diplomatic Quests
  - Form alliance with X tribes
  - Maintain peace for X days
  - Recruit X new members
  - Achieve X reputation level


- Social Quests
  - Achieve X forum posts
  - Complete X member-to-member trades
  - Reach X% member activity rate
  - Host X tribe events

#### Quest Rewards
- Tribe experience points
- Collective resource bonuses
- Temporary tribe buffs
- Cosmetic unlocks
- Gold for tribe treasury
- Special titles or badges
- Unlock new features

Quest difficulty scales with tribe size and level. New quests available weekly.

#### Quest Mechanics & Anti-Abuse
- **Eligibility**: Only actions performed after quest start count; prevents stockpiling completions.
- **Participation Credit**: Members earn personal contribution credit (for titles/badges) proportional to their share of progress.
- **Scaling**: Targets auto-scale with active member count and average tribe level; hard floors to keep quests meaningful.
- **Cooldowns**: Same quest type cannot repeat within 3 rotations; weekly rotation includes 2 random, 1 military, 1 economic, 1 social/diplomatic.
- **Progress Tracking**: Live progress bar with per-member breakdown; hover reveals top contributors and time remaining.
- **Auto-Fail Conditions**: Going below minimum active members or dropping under 50% activity pauses progress; auto-fails after 24h if not recovered.
- **Anti-Cheese Rules**: No credit for attacking alt tribes, zero-point villages, or resource trades below fair-market thresholds; repeated trades between same pair within 1h are capped.
- **Buff Delivery**: Rewards enter a tribe queue; leaders choose activation window (start/end time) within 7 days to sync with wars.
- **Catch-Up Bonus**: If tribe fails two quests in a row, next set spawns with -15% targets but reduced rewards (-25%).
- **Notifications**: Start/50%/complete/fail events broadcast to tribe feed and optional push/Discord hooks.

### Tribe Achievements

Permanent accomplishments displayed on tribe profile:

#### Achievement Categories
- Military Achievements
  - First Blood (first war victory)
  - Conqueror (100 villages conquered)
  - Defender (500 attacks repelled)
  - Warlord (10 wars won)
  - Unstoppable (50-win streak)

- Growth Achievements
  - Founding Fathers (reach 10 members)
  - Growing Empire (reach 50 members)
  - Legendary Tribe (reach level 25)
  - Point Milestone (1M, 10M, 100M points)

- Diplomatic Achievements
  - Peacekeeper (maintain 5 alliances for 30 days)
  - Diplomat (successfully negotiate 10 treaties)
  - United Front (form mega-alliance with 5+ tribes)


- Social Achievements
  - Active Community (1000 forum posts)
  - Loyal Members (10 members for 90+ days)
  - Generous (donate 10M resources to storage)
  - Mentor (successfully train 50 new members)

- Special Achievements
  - Survivor (exist for 365 days)
  - Comeback (recover from near-disbandment)
  - Underdog (defeat tribe 2x your size)
  - Perfect Season (complete all monthly quests)

Achievements grant:
- Prestige points
- Cosmetic rewards
- Tribe bonuses
- Bragging rights
- Leaderboard rankings

---

## Diplomacy States

### Diplomatic Relationship Types

#### 1. Neutral (Default)
- No formal relationship
- Standard gameplay rules apply
- Can attack freely
- No shared information
- No special UI indicators

Gameplay Effects:
- Normal attack rules
- No report sharing
- Gray color on map
- No diplomatic penalties


#### 2. Allied
- Formal alliance between tribes
- Requires mutual agreement
- Strongest positive relationship

Gameplay Effects:
- Cannot attack allied tribe members (blocked in UI)
- Allied villages show in green on map
- Can view allied tribe's public forum
- Shared intelligence access (if enabled)
- Can send support troops with -20% travel time
- Battle reports against common enemies can be shared
- Allied tribe members show with green name tags
- Joint war declarations possible
- Shared victory points in wars
- Can coordinate attacks in planning tools
- Reduced marketplace fees (if tech researched)
- Alliance chat channel
- Combined leaderboard rankings (optional)

Requirements:
- Both tribes must agree
- Minimum 24-hour waiting period
- Cannot ally with tribe you're at war with
- Maximum 5 alliances per tribe

Breaking Alliance:
- 48-hour notice period
- Becomes neutral after notice expires
- Reputation penalty
- Shared intelligence access revoked
- All support troops recalled


#### 3. Non-Aggression Pact (NAP)
- Agreement not to attack each other
- Less formal than alliance
- More flexible arrangement

Gameplay Effects:
- Attacks on NAP members show warning (but not blocked)
- NAP villages show in blue on map
- Limited intelligence sharing (optional)
- NAP tribe members show with blue name tags
- Breaking NAP triggers reputation penalty
- No shared military coordination
- Can still attack, but with consequences
- Diplomatic incident if attack occurs

Requirements:
- Mutual agreement
- Can have unlimited NAPs
- Minimum duration: 7 days
- Can be cancelled with 24-hour notice

Breaking NAP:
- Reputation penalty
- Becomes neutral immediately
- Other tribes notified of NAP break
- Temporary "Oathbreaker" status

#### 4. Enemy (War)
- Official state of war
- Can be one-sided or mutual
- Tracked in war system

Gameplay Effects:
- Enemy villages show in red on map
- Enemy tribe members show with red name tags
- Attacks on enemies grant bonus war points
- War statistics tracked
- Special war UI and tools available
- Conquests against enemies worth more prestige
- War leaderboards active
- Can set war goals and objectives
- Victory conditions tracked


Requirements:
- Can be declared unilaterally
- Costs gold and resources
- Announcement sent to all members
- Logged in diplomatic history

Ending War:
- Mutual peace treaty
- Surrender by one side
- Automatic end after 30 days of inactivity
- Victory conditions met

#### 5. Vassal
- Subordinate tribe under protection
- Asymmetric relationship

Gameplay Effects:
- Vassal cannot declare war without overlord approval
- Overlord provides military protection
- Vassal pays tribute (resources) regularly
- Overlord can view vassal intelligence
- Vassal shows in purple on overlord's map
- Overlord can veto vassal diplomatic actions
- Vassal receives defensive bonuses when overlord supports
- Shared war declarations (vassal auto-joins overlord wars)

Requirements:
- Vassal must be significantly smaller (< 50% overlord size)
- Mutual agreement or surrender terms
- Tribute amount negotiated
- Minimum 30-day term

Breaking Vassalage:
- Vassal can rebel (triggers war)
- Overlord can release vassal
- Automatic release if vassal grows too large
- Can be ended by mutual agreement


#### 6. Protectorate
- Protective relationship for new/small tribes
- Similar to vassal but more equal

Gameplay Effects:
- Protector provides military support
- Protectorate maintains independence
- Protector cannot attack protectorate
- Protectorate shows in light blue on protector's map
- Reduced tribute requirements
- Protector gains prestige for protecting
- Protectorate can request assistance
- Shared defensive coordination

Requirements:
- Protectorate must be small (< 25 members)
- Mutual agreement
- Optional tribute
- Minimum 14-day term

Ending Protectorate:
- Automatic graduation when protectorate reaches size threshold
- Can be ended by mutual agreement
- Protector can abandon (reputation penalty)

### Diplomatic Actions Table

| Action | Who Can Initiate | Requirements | Duration | Cost |
|--------|-----------------|--------------|----------|------|
| Alliance | Diplomat+ | Mutual agreement | Indefinite | 10,000 gold |
| NAP | Diplomat+ | Mutual agreement | 7+ days | 5,000 gold |
| War Declaration | Co-Leader+ | None (unilateral) | Until peace | 25,000 gold |
| Peace Treaty | Diplomat+ | Mutual agreement | Permanent | 15,000 gold |
| Vassal Agreement | Founder/Co-Leader | Size difference, mutual | 30+ days | 20,000 gold |
| Protectorate | Co-Leader+ | Size limit, mutual | 14+ days | 5,000 gold |
| Break Alliance | Co-Leader+ | 48h notice | Immediate | Reputation |
| Break NAP | Diplomat+ | 24h notice | Immediate | Reputation |


---

## War & Peace Mechanics

### War Declaration System

#### Declaring War

Process:
1. Leadership proposes war declaration
2. Optional: Internal tribe vote (if enabled in settings)
3. Pay declaration cost (gold + resources)
4. Set war goals and objectives
5. System announces war to both tribes
6. War officially begins after 12-hour preparation period

Declaration Requirements:
- Must have Co-Leader+ rank
- Tribe must be level 3+
- Cannot declare war if already in 3+ active wars
- Cannot declare on allied tribes
- Must wait 7 days after previous war with same tribe

War Goals (Optional):
- Territorial: Conquer X villages in region Y
- Dominance: Reduce enemy to X% of current size
- Revenge: Specific retaliation for past actions
- Elimination: Disband enemy tribe
- Custom: Player-defined objectives

#### War Tracking

War Statistics Dashboard:
- Total attacks launched (both sides)
- Total defenses (both sides)
- Villages conquered (both sides)
- Units killed (both sides)
- Resources plundered (both sides)
- War points earned (both sides)
- Current war score
- Timeline of major events
- Individual member contributions


War Points System:
- Successful attack: 10-100 points (based on damage)
- Successful defense: 5-50 points
- Village conquest: 500 points
- Village liberation: 300 points
- Enemy noble killed: 200 points
- Major battle victory: 50-500 points

War Score Calculation:
- Weighted combination of all war statistics
- Factors in tribe size difference (underdog bonus)
- Updates in real-time
- Determines war outcome if no formal peace

#### War Leaderboards

Multiple leaderboard categories:

Tribe War Leaderboards:
- Overall War Score (current wars)
- Total Wars Won (all-time)
- Longest War Streak
- Most Dominant Victory
- Best Underdog Victory
- Most Active Warmongers

Individual War Leaderboards:
- Most War Points (current war)
- Most Attacks Launched
- Best Defender
- Most Villages Conquered in War
- Most Enemy Units Destroyed

Seasonal War Rankings:
- Monthly war champions
- Seasonal war totals
- Year-end war awards


### Peace & Treaty System

#### Peace Treaty Negotiation

Negotiation Process:
1. One side proposes peace terms
2. Terms include:
   - Resource reparations
   - Territory concessions
   - NAP duration
   - Prisoner exchanges (if system exists)
   - Tribute payments
   - Public apology (optional)
3. Other side can accept, reject, or counter-offer
4. Up to 5 rounds of negotiation
5. If accepted, treaty takes effect immediately

Peace Treaty Types:

White Peace:
- No winner declared
- No reparations
- Return to neutral status
- No penalties

Conditional Peace:
- Winner demands terms
- Loser accepts conditions
- Terms enforced by system
- Breaking terms triggers automatic war

Negotiated Peace:
- Mutual compromise
- Both sides make concessions
- Often includes NAP period
- May include alliance clause

#### Surrender Mechanics

Unconditional Surrender:
- Losing tribe admits total defeat
- Winner dictates all terms
- May include:
  - Massive resource transfer
  - Territory cession
  - Vassalization
  - Disbandment requirement
  - Member transfer
- Severe reputation penalty for surrendering tribe
- Major prestige gain for victor


Conditional Surrender:
- Losing tribe negotiates terms
- Less severe than unconditional
- Maintains some dignity
- Faster end to war

Surrender Requirements:
- Must have Founder or Co-Leader approval
- Optional: Internal tribe vote
- Cannot surrender in first 48 hours of war
- Surrender terms must be accepted by victor

#### War Consequences

For Winners:
- Prestige and reputation gain
- War chest (bonus resources)
- Territory gains
- Recruitment boost (attract quality players)
- Achievements and titles
- Leaderboard recognition
- Tribe morale boost (+5% production for 7 days)

For Losers:
- Reputation loss
- Potential member loss (desertion)
- Territory loss
- Resource reparations
- Morale penalty (-5% production for 7 days)
- Recruitment difficulty
- May trigger internal conflict

For Both:
- War exhaustion (temporary debuff after long wars)
- Economic impact from military spending
- Diplomatic reputation changes
- Historical record in tribe profile

#### Automatic War Resolution

Wars automatically end after:
- 90 days with no activity from either side
- One tribe disbands
- Mutual agreement to end
- Victory conditions met
- Server admin intervention (rule violations)


---

## Coordination Tools

### Mass Communication

#### Mass Mail System
- Send messages to all tribe members simultaneously
- Priority levels (urgent, normal, info)
- Delivery confirmation tracking
- Read receipts
- Reply-to options
- Scheduled sending
- Templates for common messages
- Attachment support (battle plans, maps)
- Filter recipients by rank, activity, location

Permissions:
- Co-Leader+: Unlimited mass mails
- War Officer: Military-related only
- Diplomat: Diplomatic announcements only
- Recruiter: Recruitment messages only

Rate Limits:
- Maximum 5 mass mails per day (to prevent spam)
- Critical messages bypass limit
- Emergency override for Co-Leaders

#### Alert & Ping System

Alert Types:
- Emergency Alert (red): Under attack, need immediate help
- Military Alert (orange): Coordinated operation starting
- Diplomatic Alert (blue): Important diplomatic development
- General Alert (yellow): Important tribe news
- Social Alert (green): Events, celebrations

Alert Features:
- Push notifications to online members
- In-game popup
- Sound notification
- Persistent until acknowledged
- Can @mention specific members or ranks
- Location ping on map
- Countdown timers for time-sensitive alerts

#### Tribe Chat System

Real-time chat channels:
- General Chat (all members)
- Leadership Chat (leaders only)
- War Room Chat (military coordination)
- Ally Chat (with allied tribes)
- Rank-specific channels

Chat Features:
- Message history (30 days)
- @mentions and notifications
- Emoji and reactions
- Link sharing (battle reports, villages, maps)
- Image sharing
- Voice chat integration (optional)
- Moderation tools (mute, kick, ban)
- Chat logs for leadership
- Profanity filters
- Spam protection

### Scheduled Operations

Military Operation Planner:

Operation Creation:
1. War Officer creates operation
2. Sets operation name and description
3. Defines objectives
4. Sets launch time (with countdown)
5. Assigns participants
6. Distributes targets
7. Sends notifications

Operation Features:
- Visual timeline showing all attacks
- Automatic attack time calculator
- Target assignment system
- Participant confirmation tracking
- Pre-operation checklist
- Launch countdown
- Real-time status updates during operation
- Post-operation analysis and reports

Operation Types:
- Coordinated Strike (simultaneous attacks)
- Wave Attack (sequential waves)
- Siege Operation (prolonged assault)
- Raid Operation (quick hit-and-run)
- Defensive Operation (coordinated defense)
- Fake Attack (deception operation)

### War Room Board & Map Tools

- **Shared Board**: Kanban-style columns (Target → Assigned → Launching → Resolving → Done) with cards for villages/objectives; drag/drop to reassign.
- **Map Overlay**: War Room layer showing pinned targets, rally points, march lines, and ETA bands; color-coded per operation.
- **Assignments**: Each card tracks responsible player(s), required troop types, and timing windows; supports backups and auto-reassign if player goes inactive.
- **Visibility**: Default visible to War Officer+ and assigned participants; optional “need-to-know” mode hides unassigned targets.
- **Intel Links**: Cards attach latest scout reports and confidence level; stale intel badge after configurable TTL.
- **Checklists**: Per-target checklist (launch confirmed, landing synced, support timed, fakes sent); auto-updates when reports arrive.
- **Timers & Sync**: Local time plus server time; one-click “match landing to X:YY” helper; warns if travel time slips past window.
- **Audit Trail**: Edits and reassignments logged with user and timestamp; leadership can roll back changes within 10 minutes.
- **Export/Share**: Generate text summary for chat/Discord and printable plan PDF; redacted mode strips player names for ally sharing.
- **Anti-Leak Tools**: Watermarked views per user, screenshot detection warning, optional delayed reveal of final targets until T-30m.


### Target Management

#### Target Lists

Target List Features:
- Create multiple target lists (by region, priority, type)
- Add enemy villages with notes
- Priority ranking (high, medium, low)
- Status tracking (scouted, attacked, conquered, abandoned)
- Assignment tracking (who's attacking what)
- Intelligence integration (defense info, resources)
- Filtering and sorting
- Export to CSV
- Share with specific members or ranks

Target Information:
- Village coordinates
- Owner name and tribe
- Estimated defenses
- Last scouted date
- Attack history
- Strategic value
- Assigned attacker(s)
- Attack deadline
- Notes and special instructions

#### Assignment System

Automatic Target Assignment:
- Leadership sets criteria (member strength, location, availability)
- System suggests optimal assignments
- Members receive notifications of assignments
- Can accept, decline, or request reassignment
- Tracks completion status

Manual Assignment:
- Officers assign specific targets to specific members
- Include instructions and requirements
- Set deadlines
- Track progress
- Provide support if needed
