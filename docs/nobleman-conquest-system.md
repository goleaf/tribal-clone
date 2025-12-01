# Envoy Conquest System (Influence & Control)

This replaces legacy loyalty-drop nobles with an influence-driven conquest loop to avoid coin minting, morale/luck parallels, and named-unit overlap.

## Core Concepts
- **Control Meter (0–100)**: Tracks attacker influence on a village. Starts at 0 for external forces; defenders passively regenerate control back to 0 when attacker pressure ends.
- **Capture Condition**: When attacker control reaches 100 and stays uncontested for a configured uptime (default 15 minutes), ownership flips and control resets to 0 under the new owner.
- **Envoy Unit**: Renamed from Noble. Trains via the Academy using influence crests (not coins). Envoys establish control links; multiple Envoys increase control rate but never stack randomness.
- **No Randomness**: Control accrues at fixed rates; morale/luck bands are disabled for conquest resolution.
- **Resistance**: Defending garrisons and structures (e.g., Keep/Church) apply a resistance value that slows or blocks control gain until removed.

## Conquest Flow
1) **Battle Resolution**: Standard combat resolves; attacker must win and at least one Envoy survives.  
2) **Establish Control Link**: If conditions pass, a control link is placed on the target village with an initial control value (default 25).  
3) **Control Accrual**: Control increases at `BASE_CONTROL_RATE` per minute while the attacker has an active control link and resistance ≤ attacker pressure. Additional surviving Envoys add a flat bonus to rate.  
4) **Contestation**: If defenders return and their resistance exceeds attacker pressure, control gain pauses; if it exceeds by a threshold, control decays back toward 0.  
5) **Capture**: When control reaches 100 and remains ≥100 for `CAPTURE_UPTIME_SECONDS` (default 900s), ownership transfers, all control links reset, and defending support is returned home (configurable).  
6) **Post-Capture Cooldown**: The village cannot be re-captured for `RECAPTURE_COOLDOWN_MINUTES` (default 60); further Envoy attempts during this window only apply damage, not control.

## Key Parameters (configurable)
- `BASE_CONTROL_RATE_PER_MIN`: 10 (control points per minute with one surviving Envoy and zero resistance)
- `ENVOY_RATE_BONUS_PER_UNIT`: 2 (additional control points/min per extra surviving Envoy, capped)
- `MAX_ENVOY_RATE_BONUS_CAP`: 3 Envoys worth of bonus
- `RESISTANCE_THRESHOLD_TO_DECAY`: 10 (if defender resistance exceeds attacker pressure by this amount, control decays)
- `CONTROL_DECAY_PER_MIN`: 8 (decay rate when overpowered)
- `CAPTURE_UPTIME_SECONDS`: 900 (control must be maintained at 100 for this duration)
- `RECAPTURE_COOLDOWN_MINUTES`: 60
- `INITIAL_CONTROL_ON_LINK`: 25

## Unit & Cost Changes
- **Envoy (formerly Nobleman)**: Uses influence crests instead of minted coins. No coin system remains in conquest.
- **Academy Rename**: Academy trains Envoys using influence inputs; terminology updated across UI/reports to avoid legacy naming.

## Reports & UI
- Battle reports show: control before/after, resistance present, whether a control link was established, and remaining uptime to capture when at 100.
- Map icons: control-linked villages display a pulsing ring and countdown chip for remaining uptime.
- No loyalty values or random drops are displayed.

## Edge Rules
- Support-only attacks cannot establish control.
- Spy/Scout missions never create control links.
- Barbarian/Forsaken villages follow the same control rules (no special coin/loyalty paths).
- Morale/luck modifiers are disabled during control calculations.
- If all attacker pressure is removed, control naturally decays toward 0 at `CONTROL_DECAY_PER_MIN`.

## Testing Checklist
- [ ] Envoy win establishes control and starts uptime countdown at 100.  
- [ ] Multiple Envoys increase control rate up to cap; no randomness in increments.  
- [ ] Defender resistance pauses/decays control appropriately.  
- [ ] Capture flips ownership after uptime; control resets and recapture cooldown applies.  
- [ ] Attempts during recapture cooldown do not flip ownership.  
- [ ] Reports and UI show control values (not loyalty) and use Envoy terminology.  
- [ ] No coin references exist in Academy/Envoy flows.  
- [ ] Barbarian/Forsaken capture follows the same control logic.  
