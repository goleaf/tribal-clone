# New Player Experience (NPE) & Tutorial — Medieval Tribal War Browser MMO

## Onboarding Goals
- Teach essentials within 30–60 minutes: resource collection, building/upgrade flow, troop recruitment, scouting, basic attack/support, and protection rules.
- Convey fantasy: you are a fledgling chieftain securing a foothold in contested lands, supported by a tribe.
- Set emotions: safety and clarity (first 10–15 minutes), gradual empowerment (first successful scout/raid), and social belonging (tribe invite), without overwhelming.
- Align expectations: PvP matters but is gated; losses are recoverable; teamwork beats solo grind.

## First-Session Flow (Scripted Path with Branches)
1. **Welcome & Placement**
   - Auto-place in starter sector; cinematic splash with herald character (e.g., “Warden Edda”).
   - Player chooses banner/colors; optional name later (skip allowed, default name provided temporarily).
2. **Guided Camera & UI Tour (2–3 steps)**
   - Highlight resource bar, build button, rally point, mini-map/map button.
   - Quick “This is safe ground; no one can attack you during the tutorial.”
3. **Build Resource Fields**
   - Popup: “Upgrade Lumber Yard to level 1.” Instant or 30s build with speed-up provided. Reward: Wood/Clay/Iron + small banner cosmetic.
   - Branch: If user closes popup, a subtle nudge remains; helper bubble persists until task done.
4. **Storage & Wall Intro**
   - Task: Upgrade Storage to 1; start Wall 1. Reward: resource bundle; tip about protection and walls.
5. **Rally Point & First Scout**
   - Unlock Rally Point; send scripted scout to nearby barbarian hamlet (cannot fail). Shows travel timer and returns within 30–60s.
   - Popup explains scouting vs attacking; shows report preview.
6. **Troop Recruitment**
   - Build Barracks to level 1 (instant token); recruit 10 Spears preset. Reward: pop-up “Troops in queue” with ETA and a speed token (5m) once per tutorial.
   - Branch: If player tries to recruit more than pop allows, inline error explains population.
7. **First Raid (Safe)**
   - After scout returns, auto-pin target and prefill attack with tutorial preset (10 Spears).
   - User must confirm attack; confirmation shows travel time and that beginner protection remains (attacking barb does not break it).
   - Battle is scripted win; loot delivered; report shown with highlights on loot, losses (0), and wall (0).
8. **Defense Primer**
   - Prompt to upgrade Wall to 2; reward: watchtower tooltip tease and small resource refund.
   - Tip about dodging/stacking appears but can be dismissed; link to advanced tutorial.
9. **Social Onramp**
   - Prompt to open chat (global/region muted by default; tribe recruitment channel suggested).
   - Auto-suggest 3 nearby beginner-friendly tribes; “Apply” with one tap; optional “Let tribes invite me” toggle.
   - Branch: If player declines, set reminder after next raid; if joins, grant tribe welcome gift (cosmetic frame + small token).
10. **Session Close State**
    - Encourage queuing one more build/recruit; surface “Beginner protection ends in Xh” and “You can relocate within Yh” tooltips.
    - Offer “Set notifications for attacks/builds” opt-in; show summary of what was learned.

## Early Quest Chain (Pre-PvP) 
| Step | Quest | Requirement | Reward |
| --- | --- | --- | --- |
| 1 | Secure Supplies | Upgrade Lumber/Clay/Iron to level 1 | 5m build token (one-time), resources |
| 2 | Shelter the Stockpile | Build Storage to level 1 | Resource bundle, Storage tooltip unlock |
| 3 | Raise the Rally | Build Rally Point | Scout unit unlocked, map bookmark set |
| 4 | Eyes on the Wilds | Send a scout to marked barbarian hamlet | Report tutorial, 5 scouts granted |
| 5 | Muster Spearmen | Train 10 Spears | Cosmetic badge, pop explanation |
| 6 | First Foray | Attack the scouted barb with preset | Loot, small queue voucher |
| 7 | Shore Up Walls | Upgrade Wall to 2 | Resource bundle, wall mechanic tooltip |
| 8 | Balance the Stores | Spend resources to avoid cap (build or trade) | Reroll token for quests |
| 9 | Join Voices | Post “Hi” in region or tribe chat OR send aid request | Profile frame, chat safety tip |
| 10 | Choose Your Path | Branch A: Attack another barb; Branch B: Run 2 scouts on neutrals; Branch C: Build to level 3 economy building | Branch-specific cosmetics + small tokens |
| 11 | Prepare for Neighbors | Bookmark 2 nearby villages and set 1 rally preset | Map marker pack |
| 12 | Ready for PvP | Read “Protection rules” tooltip and acknowledge | Unlocks opt-in early protection exit button |

- Quests unlock sequentially but allow Branch 10 choice. Completing any path grants progress; completion of all three grants bonus cosmetic.

## Beginner Protection
- **Length:** 72 hours or until reaching a points threshold (e.g., 500–800 points), whichever comes first.
- **Rules:**
  - Cannot be attacked; outgoing attacks vs players disabled. Attacking barb/POI allowed and does NOT break protection.
  - Joining a tribe allowed; accepting tribe support limited to low troop counts to prevent pushing.
  - Trading allowed within storage caps; heavy aid from high-power players taxed or blocked.
- **Early Exit:** Button appears after tutorial; confirm dialog explains risks; 12h cooldown to re-enable is disallowed (once off, off for good).
- **Edge Cases:**
  - If player attacks another player (only possible after toggling off), protection ends immediately.
  - If a protected player sends support to a non-protected player, support moves but player stays protected; attackers cannot target protected player’s village.
  - Relocation allowed during protection; blocked if hostile commands inbound (none should be during protection, but check support/tribe moves).

## Guided Social Integration
- **Auto-Suggested Tribes:** Based on region, language, diplomacy stance, and “rookie-friendly” flag. Display 3 suggestions with short blurbs.
- **One-Tap Apply/Join:** If tribe settings allow auto-join for protected players, button says “Join.” Otherwise “Apply” with prefilled polite message.
- **Chat Nudges:** Toast after first raid: “Say hi in Region to learn tips (safe).” Provide canned messages to reduce friction.
- **Tribe Invites:** Allow tribes to send invites to protected players; acceptance triggers welcome mail and starter cosmetic.
- **Mentor Pings (Optional):** Volunteer mentors can respond to newbie questions; safe chat filters and rate limits applied.

## Early Mistake Protection
- **Confirmation Walls:** Double-confirm destructive actions: deleting queues, demolishing key buildings, abandoning village (disabled during protection).
- **Resource Overspend Warnings:** If action would zero key resource, show warning and suggest alternative (trade/balance).
- **Attack Safeguard:** Before sending first PvP attack, show modal summarizing protection status and expected retaliation risk; require explicit acknowledgment.
- **Queue Safety:** Prevent queuing troops that exceed population; highlight how to build Farm/Granary.
- **Shield on Devastation:** If player loses >50% troops in early game PvE (rare), give a one-time recovery pack and short shield.

## Accessibility & Clarity
- **Tooltips:** Inline for morale, loyalty/allegiance, wall effects, resource production, timers. Dismissable; “learn more” opens help page.
- **Hint System:** Contextual hints appear when hovering/pausing on empty state (e.g., “Build queue empty — start a structure”).
- **Help Pages:** Searchable help center with short articles and mini-diagrams (text); accessible from ? icon in top bar.
- **Optional Advanced Tutorials:**
  - Timing attacks/supports and dodging.
  - Scouting fidelity and counter-scouting.
  - Economy efficiency (storage caps, balance tools).
  - Tribe ops planner basics.
- **Readable UI:** High-contrast option, adjustable font size, reduced motion toggle, colorblind-friendly icons for diplomacy and commands.

## Narrative Walkthrough — First Evening
- **00:00–05:00:** Player lands, meets Warden Edda, picks banner. Builds Lumber Yard/Clay Pit/Iron Mine to level 1 with guided clicks. Receives first resource reward and sees build timers finish quickly.
- **05:00–10:00:** Builds Storage and Wall 1; tooltip explains protection. Rally Point appears; Edda prompts: “Let’s see who’s nearby.”
- **10:00–15:00:** Sends scripted scout to marked barbarian hamlet. Timer shows 00:01:00; returns with report showing resources. Player clicks “Next: Raid.”
- **15:00–20:00:** Trains 10 Spears with provided resources. Queue shows 2m; tutorial offers one-time 5m speed token to finish. Player uses it; gets “Ready to march” toast.
- **20:00–25:00:** Sends first raid via prefilled preset; confirmation shows attack won’t break protection. Raid returns successful; loot added; report window highlights key info.
- **25:00–35:00:** Quest nudges to upgrade Wall 2 and place map bookmarks. Player adds two bookmarks and names them.
- **35:00–45:00:** Chat prompt appears with canned “Hi! New here.” Player sends; receives friendly response. Tribe suggestions pop up; player applies to “Green Shire.”
- **45:00–60:00:** While waiting for tribe response, player sets another build (Town Hall 2) and recruits more Spears. Tutorial reminds: “Protection ends in 71h; you can relocate once.” Summary modal recaps learned systems and suggests advanced tutorials. Player logs off with queues running and a small login-bonus token scheduled for next day.

