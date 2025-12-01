# UX Tone & Voice Guide — Tribal Strategy MMO

Purpose: keep strings clear, fair, and tribe-first across UI/help/localization. Use this as a reference for new copy and reviews.

## Tone Principles
- **Clarity first:** short sentences, active voice, surface the “why” and next step (e.g., “Farm cap full. Recruit fewer units or upgrade Farm.”).
- **Fair & respectful:** avoid blame; state rules/failures plainly with guidance (“Blocked: target is under beginner protection.”).
- **Tribe-first:** emphasize teamwork and shared intel (“Ask your tribe for support” vs “You are alone.”).
- **Calm urgency:** acknowledge pressure without alarmism (“Incoming attack in 3:12; call support or dodge.”).
- **Consistency:** use the same term for the same concept; prefer verbs over jargon.

## Style Rules
- Prefer short, direct sentences; avoid filler and exclamation marks.
- Use second person (“you/your”) and present tense.
- Include actionable hint on errors and locks; show retry/requirements when possible.
- Keep numbers explicit (units, levels, times); avoid vague “soon/low/high.”
- Respect accessibility: no all-caps; avoid color-only meaning; keep reading level simple.

## Terminology
- Use approved terms from `docs/ip-glossary.md`; avoid banned legacy names.
- Standardize key nouns: “Standard Bearer” (conquest), “Allegiance” (loyalty), “Watchtower”, “Hall of Banners”, “Support” (defense), “Raid” (quick plunder), “Siege” (wall/building damage).
- Avoid legacy/jargon: no “noble trains”, “snob”, “fake nuke” in UI; use “conquest wave”, “decoy”, “clearing wave.”

## Patterns by Context
- **Errors/Blocks:** State cause + fix. “Cannot send: village protected (beginner). Try after protection ends.” Include code if helpful (e.g., `ERR_PROTECTED`).
- **Warnings:** Lead with fact, then action. “Storage almost full. Spend or trade resources to avoid loss.”
- **Reports:** List modifiers applied (luck, morale, night, vault protection), show deltas, and note redactions (“Scouts died — defender intel hidden.”).
- **Timers/Queues:** Show ETA and finish time; avoid “soon”. Offer next action (“Queue another build”).
- **Notifications:** Keep one-line summaries; link to detail screens; avoid spammy tone.

## Localization Notes
- Avoid idioms and puns; keep sentences simple for translation.
- Keep placeholders explicit: `{village}`, `{time}`, `{amount}`; avoid concatenation that breaks grammar.
- Document gender/number where relevant; prefer neutral phrasing.

## Review Checklist
- Is the message clear on what happened and what to do next?
- Does it use approved terminology and avoid banned jargon?
- Are numbers and requirements explicit?
- Is the tone respectful and tribe-first?
- Would the message still make sense when translated literally?
