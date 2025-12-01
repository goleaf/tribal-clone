# Tribes & Diplomacy Systems — Medieval Tribal War Browser MMO

## Tribe Structure & Identity
- **Creation:** Any player (meeting level/quest prerequisite) can found a tribe by choosing name, 2–4 letter tag, banner/sigil, and recruiting policy (open, application, invite-only).
- **Size Options:** World-configurable caps (e.g., 25–50). Minimum size for certain features (e.g., tribe tech unlocks at 5 members). Alliance limits to prevent mega-coalitions.
- **Tribe Progression:**
  - **Tribe Level:** Earned via member activity (battles, captures, quests, aid) and tribe quests; unlocks cosmetic perks, forum slots, small utility (extra preset slots), never combat stats unless world-specific tribe tech is enabled with caps.
  - **Reputation/Prestige:** Displayed on profile; earned from wars won, endgame placement, seasonal events.
- **Identity Features:** Custom banner, emblem frames, profile backgrounds, forum theme, map markers style, motto/tagline, recruitment blurb, language/region tags.

## Roles & Permissions
| Role | Typical Permissions | Responsibilities |
| --- | --- | --- |
| Founder | All permissions; edit roles; disband/transfer ownership | Vision, diplomacy, succession |
| Leader/Co-Leader | Invite/kick, set diplomacy, manage ops, manage forums, spend tribe resources/tech | Strategy, coordination |
| War Officer | Create ops, assign targets, send tribe pings, view war stats, manage shared intel | Offensive/defensive planning |
| Diplomat | Propose/accept diplomacy states, send/receive treaties, manage vassal/protectorate agreements | External relations |
| Logistics Officer | Manage tribe storage/aid pool, approve large sends, set aid caps, manage shared presets | Resource/support logistics |
| Recruiter | Approve applications, send invites, post recruitment mail | Growth and onboarding |
| Intel Officer | Curate shared reports, tag/flag intel, manage map markers | Information integrity |
| Forum/Comms Mod | Pin/lock threads, moderate chat/forums | Culture and safety |
| Member | View intel, join ops, support allies, contribute to quests | Core participation |
| Recruit/Trial | Limited access to intel and ops, cannot invite, limited aid | Onboarding and evaluation |

- Permissions are granular toggles (e.g., “view war board,” “create marker,” “spend tribe coins,” “edit announcements”).

## Tribe Features
- **Internal Forums & Announcements:** Threads by category (ops, strategy, social); pinned announcements; role-restricted boards; markdown/basic formatting; attachment of reports and markers.
- **Member Notes:** Private notes per member (for leadership) and self-notes (personal reminders); notes visible by permission.
- **Shared Intel:** Central repository of battle/scout reports; tagging, search, filters (by target, region, enemy tribe). Map overlays from shared intel showing last seen troop levels/structures.
- **Tribe-Wide Tech (optional per world):** Small capped bonuses (e.g., +2% build speed, +5% scout defense) funded by tribe currency/quests; cosmetic tech lines (banners, trails).
- **Shared Storage/Aid Pool:** Tribe resource bank with contribution caps; withdrawal rules by role; audit logs; distance taxes applied to prevent pushing.
- **Tribe Quests:** Collective objectives (defend allies N times, capture relics, contribute resources). Rewards: cosmetics, tribe coins, queue vouchers.
- **Tribe Achievements:** Season/world badges (wars won, relics held, wonders built), displayed on tribe profile and member profiles.
- **Calendar:** Shared schedule for ops, events, and war windows; timezone-aware.

## Diplomacy States & Effects
| State | Gameplay Effects | UI/Map Effects |
| --- | --- | --- |
| Ally | Support allowed; shared vision (optional per world); friendly fire disabled; resource trades tax-reduced | Map color green/blue; ally badge on villages; reports shareable by default |
| Non-Aggression Pact (NAP) | Attacks blocked or heavily penalized; support optional; shared intel optional | Map color teal; timers showing NAP expiration; warning on attack attempts |
| Enemy/War | Attacks/support unrestricted; war stats tracked; diplomacy penalties removed | Red map color; war banner; war board in tribe UI |
| Neutral | Default; normal rules | Standard colors |
| Vassal/Protectorate (optional) | Vassal can’t attack overlord/overlord allies; tribute rules; protection from overlord | Special map border, crest overlay; UI note on tribute status |
| Ceasefire (Timed) | Temporary halt of attacks; support rules defined by treaty | Grey overlay; countdown badge |

- **Practical Changes:** Attack prompts warn on NAP/ally; support auto-accepted for allies; shared reports visibility per state; diplomacy locks have cooldowns.

## War & Peace Mechanics
- **Declaration:** Leader/Diplomat can declare war with optional casus belli note; triggers public announcement and war board creation.
- **Tracking:** War stats (villages captured/lost, kill/death population, wall damage, relics held, ops executed). Daily/weekly summaries.
- **Surrender/Treaty:** Offer surrender with terms (tribute, ceasefire duration, territory relinquish). Accepting ends war and creates timed ceasefire; violations tracked.
- **End Conditions:** Mutual agreement, timer with inactivity threshold, victory condition (e.g., X villages captured) reached.
- **Consequences:** Breaking treaties flags dishonor score (reputation hit); can affect matchmaking/recruit recommendations.

## Coordination Tools
- **Mass Mail & Broadcasts:** Role-limited; targeted by role or subgroup (frontline, defenders, recruits). Templates with placeholders for coords/times.
- **Ping/Alert System:** Village-specific or global; severity levels (info/alert/critical). Cooldowns to prevent spam. Mobile/browser push optional.
- **Scheduled Operations:** Ops planner with send-time calculator, wave sequencing, fake/main tagging, noble train planning, timezone helper. Assign targets to players; mark completion and outcomes.
- **Target Lists:** Shared lists with priority, intel status, last scouted time, required forces. Export to map markers.
- **Assignment Tools:** Assign defense groups to villages; assign scouts to sectors; track commitments and fulfillment.
- **Shared Presets:** Tribe-wide attack/support presets available to roles; locked to prevent editing by non-owners.

## Social Systems
- **Recruiting Flows:**
  - Public tribe listing with filters (region, language, playstyle, requirements).
  - Auto-recommendations to new players based on proximity and tribe openness.
  - Application forms with questions (availability, goals); recruiters review with quick accept/deny.
- **Invites:** Role-gated; optional auto-accept for protected/new players; invite cooldown to reduce spam.
- **Onboarding:** Welcome mail template, trial role assignment, mentor pairing, checklist for new members.
- **Mergers/Splits:** Tools to bulk-invite another tribe; merge retains history; tags/emblems can be combined or retired; split creates child tribe with subset of members and forums.
- **Inactivity Handling:** Auto-flag inactive members; leadership tools to set “away” status; scheduled kicks with grace period; auto-handoff of roles if leader inactive (with confirmation by next role).

## Optional Systems & Prestige
- **Tribe Skill Trees (world-optional):** Branches for Economy/Recon/Logistics/Culture; buffs are minor and capped; respec with cooldown.
- **Seasonal Tribe Events:** Tribe-only challenges with cosmetic rewards (forum skin, marker pack); seasonal tribe leaderboards.
- **Tribe Capital/City:** Upgradeable cosmetic hub; shows banners, trophies, seasonal statues; may grant non-combat utilities (extra preset slots, cosmetic trails) if enabled.
- **Prestige Levels:** After season/world end, tribe gains prestige stars displayed on profile; unlocks legacy cosmetics.

## UI & Map Integration
- Diplomacy colors on map; toggle legend; filter by state.
- Tribe profile shows banner, tag, achievements, wars, members by role, recruitment status, timezone distribution.
- War board with timeline, ops list, current fronts (heatmap), and war goals.
- Intel overlay showing last-scouted timestamps and priority targets.

## Implementation TODOs
- [ ] Tribe config schema: roles/permissions matrix, recruitment policy, diplomacy settings, alliance caps; audit changes.
- [ ] Diplomacy state machine: enforce single state per pair, minimum durations, cooldowns, and overrides with logging.
- [ ] War tracking backend: store declarations, objectives, stats (kills, captures, relics), and timelines; expose API for war board.
- [ ] Tribe storage/aid pool: contribution/withdraw rules with audit logs and distance tax hook; caps per role/world config.
- [ ] Ops planner: endpoints for creating ops, assigning targets, and linking markers/presets; track completion and reports.
- [ ] Intel repository: shared report storage with tags/filters; permissions by role; map overlay API for intel recency.
- [ ] Recruitment pipeline: applications/invites with cooldowns, mentor assignment optional, auto-expire unreviewed apps; rookie-friendly flag surfaced in recommendations.
- [ ] Inactivity automations: detection of inactive leaders, role handoff workflow, scheduled kicks with grace and notification.
- [ ] Reputation/violation logging: treaty breaks, ceasefire violations, dishonor score; surfaced in diplomacy UI and recommendations.
- [ ] Rate limits & safeguards: cap diplomacy flips per tribe pair per 24h; require dual-approval for ally/NAP attack overrides; throttle mass mail/ping spam with role-based quotas.
- [ ] Auditability: append-only logs for role changes, storage withdrawals, treaty edits, and ops target reassignment with actor/timestamp/IP hash.

## Examples & Scenarios
- **War Declaration Flow:** Leader declares war on IronFist; system posts announcement; map turns red for enemy villages; war board lists objectives (take 3 relic shrines, capture 10 villages). War stats start tracking; pings sent to frontline members only.
- **Ceasefire Violation:** NAP with Ashen Pact ends in 48h. A member tries to attack; UI warns and blocks unless leader overrides. If violation occurs, reputation note logged; future NAP offers to others show lower trust rating.
- **Recruit Onboarding:** New player auto-recommended to “Green Shire” tribe; recruiter sees application with coords and activity window; accepts; new member gets welcome mail, set to “Recruit” role with limited intel; mentor assigned; trial lasts 7 days before auto-prompt to promote.
- **Merging Tribes:** Tribe A and B merge; bulk invite accepted; Tribe B’s forums archived as read-only; achievements combined; new emblem voted by leaders. Inactive members flagged for review.
- **Operation Coordination:** War Officer schedules OP “Dawnstrike” with send times; assigns players to targets; target list appears on map with markers; mass mail includes links; completion checkboxes and report links captured in ops log.
