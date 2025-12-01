# Village Conquest & Control

## Overview

Conquest is a staged control fight built on **breach severity** and **influence uptime**, not on legacy loyalty drops or minted coins. Attackers must break fortifications, keep influence anchors alive, and hold control long enough to trigger an automated claim pulse.

---

## Core Conquest Loop

### Control Meter
- Control ranges from **+100 (defender)** to **-100 (attacker)**.
- Control shifts only while both are true:
  1. The village has a live **Breach** (25+).
  2. An attacker has an active **Influence Anchor** (Envoy + standards) present.
- Base shift: **5–15 control/min** depending on anchor strength and contested presence.
- If no side supplies pressure, control drifts 5 control/min toward the current owner.

### Breach Severity (Siege DoT)
- Walls accrue **Breach** instead of binary HP.
  - 0–24: cosmetic cracks; control cannot shift.
  - 25–59: minor breach; slow control trickle if anchors exist.
  - 60–89: major breach; wall defense halved, control speeds up.
  - 90–100: collapse; wall bonus disabled, control rate ×2.
- Rams/siege apply Breach and a **structure DoT** that decays 5 Breach/min if no new hits land.
- Emergency repairs accelerate decay to 10 Breach/min for 5 minutes after a repair order.

### Influence Anchors (Envoys)
- When an Envoy survives a battle **and** Breach ≥ 25, it plants 2 anchors for 20 minutes.
- Anchors add **+8 control/min** while both stand; each 1,000 escort population adds +1 control/min (max +6).
- Defender garrisons:
  - >500 pop: halves incoming control.
  - >1,500 pop: freezes control until reduced.
- Additional Envoys add +1 anchor (stacking to 4) but anchors never exceed +8/min each.

---

## Special Unit: Envoy (Faction titles: Bannerlord, Voice, Legate)

| Attribute | Value | Notes |
|-----------|-------|-------|
| Training Cost | 28k wood, 24k clay, 36k iron, 45k food | No coin minting |
| Training Time | 40h | Requires Hall of Banners 18+ |
| Population Cost | 10 | High pop drain |
| Speed | 4 tiles/hour | Slow; needs escort |
| Attack / Defense | 40 / 90 | Not a combat unit |
| Influence Pressure | +8 control/min (per 2 anchors) | Scales with escort |

### Requirements
- **Hall of Banners** 18+ (1 Envoy slot per hall)
- **Academy** 15+
- **Rally Point** 10+
- **Stable** 10+
- Only one Envoy in training per Hall.

### Vulnerability
- 60% death chance if present in a losing battle.
- Anchors expire after 20 minutes; if defenders still hold, control snaps back 10 control/min toward defender.
- Envoys cannot raid or support. New training allowed 16h after death.

---

## Capture Conditions

A village flips only when:
1. **Breach Threshold**: Breach ≥ 25 (60+ recommended).
2. **Control Flip**: Control reaches **-100** and holds for 60 seconds while an Envoy anchor is active.
3. **Claim Pulse**: An Envoy inside the village auto-triggers the claim pulse; if it dies before 60s, the claim fails.
4. **Attacker Eligibility**: Has Hall of Banners in the source village, an open village slot, and is not in beginner protection.
5. **Defender Eligibility**: Not defender’s last village; not under beginner protection; conquest immunity (7 days) expired.
6. **Timing Resets**: If Breach drops below 25 or control returns to 0+, the claim attempt resets.

---

## Tactics & Timing

- **Blitz Flip**: 2–3 Envoys from different villages, high Breach (90+), anchors stacked; aim to flip in <30 minutes.
- **Siege Slowburn**: Maintain Breach 60–80 with periodic rams; 1–2 Envoys cycling anchors over several hours; safer versus counter-raids.
- **Anchor Defense**: Defenders should spike garrison above 1,500 pop to freeze control, repair to drain Breach, and snipe Envoys before the 60s hold.
- **Anti-Coin Divergence**: No coins or minting for conquest; all gating lives in buildings (Hall of Banners) and control uptime.
