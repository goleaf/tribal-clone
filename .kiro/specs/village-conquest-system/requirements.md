# Requirements Document

## Introduction

The Village Conquest & Control system enables players to capture villages from other players through a strategic, multi-phase process. Villages change ownership via a Control Meter (0-100) or Allegiance system, where Envoy units establish control links through successful attacks. The system is designed to be telegraphed and contestable, requiring attackers to break defenses, clear garrisons, land Envoys to build control, and defend the uptime window against counter-attacks and snipes.

## Glossary

- **Envoy**: A special conquest unit that carries edicts to assert control over enemy villages; anchors a control link on successful attack
- **Control Meter**: A 0-100 gauge representing attacker influence over a village; when it reaches 100 and holds through uptime, ownership transfers
- **Allegiance**: Alternative to Control Meter; represents village loyalty (0-100); drops with successful Envoy attacks until capture at 0
- **Control Link**: The active connection established by surviving Envoys that enables control gain
- **Uptime Window**: A timer (e.g., 900 seconds) that starts when control reaches 100; capture occurs if control stays ≥100 throughout
- **Hall of Banners**: The building required to train Envoys (analogous to Academy for nobles)
- **Influence Crests**: Special resources consumed when training Envoys
- **Anti-Snipe Floor**: A minimum allegiance/control value below which the meter cannot drop for a period after capture
- **Resistance**: Defender's ability to slow or reverse control gain through troop presence and defensive structures
- **Standard Bearer**: Alternative name for Envoy unit in allegiance-drop mode
- **Conquest Train**: A sequence of tightly-timed Envoy waves designed to minimize defender response windows
- **Control Decay**: The reduction of control when defender resistance exceeds attacker pressure
- **Capture Cooldown**: A grace period after capture during which the village cannot be immediately recaptured

## Requirements

### Requirement 1

**User Story:** As an attacker, I want to train Envoy units to capture enemy villages, so that I can expand my territory through strategic conquest.

#### Acceptance Criteria

1. WHEN a player has a Hall of Banners at the required level THEN the system SHALL enable Envoy training
2. WHEN a player trains an Envoy THEN the system SHALL consume influence crests, resources, and population as configured
3. WHEN a player attempts to train Envoys without meeting prerequisites THEN the system SHALL prevent training and return reason code ERR_PREREQ
4. WHERE the world has per-command Envoy limits, WHEN a player exceeds the limit THEN the system SHALL prevent the command and return ERR_CAP
5. WHEN an Envoy is trained THEN the system SHALL apply siege-speed movement rates to the unit

### Requirement 2

**User Story:** As an attacker, I want to establish control over enemy villages through successful Envoy attacks, so that I can capture them after sufficient pressure.

#### Acceptance Criteria

1. WHEN an attacker wins a battle and at least one Envoy survives THEN the system SHALL establish a control link and apply initial control gain
2. WHEN a control link is active and attacker pressure exceeds defender resistance THEN the system SHALL increase control at the configured rate per minute
3. WHEN control reaches 100 THEN the system SHALL start the uptime timer
4. WHEN control remains at or above 100 through the entire uptime duration THEN the system SHALL transfer village ownership to the attacker
5. IF the attacker loses the battle or no Envoys survive THEN the system SHALL not apply any control gain

### Requirement 3

**User Story:** As a defender, I want to resist conquest attempts through defensive structures and troop presence, so that I can protect my villages from capture.

#### Acceptance Criteria

1. WHEN defender resistance exceeds attacker pressure by the configured threshold THEN the system SHALL decay control toward zero
2. WHILE a village has high wall levels, WHEN Envoys attack THEN the system SHALL reduce Envoy survival rates
3. WHEN defenders land support troops before uptime completes THEN the system SHALL increase resistance and potentially trigger control decay
4. WHEN control drops below 100 during uptime THEN the system SHALL reset the uptime timer
5. WHEN defenders eliminate all attacking Envoys THEN the system SHALL prevent control gain for that wave

### Requirement 4

**User Story:** As a player, I want the allegiance/control system to regenerate over time, so that unconquered villages naturally recover from conquest pressure.

#### Acceptance Criteria

1. WHEN time elapses since the last allegiance update THEN the system SHALL increase allegiance by the configured regeneration rate per hour
2. WHILE allegiance regeneration is active THEN the system SHALL clamp the maximum value to 100
3. WHEN building bonuses or tribe technologies apply THEN the system SHALL multiply the base regeneration rate by configured modifiers up to the maximum multiplier
4. WHILE anti-snipe grace period is active THEN the system SHALL pause allegiance regeneration
5. WHEN a village is abandoned for the configured duration THEN the system SHALL optionally apply decay to allegiance

### Requirement 5

**User Story:** As a newly successful conqueror, I want captured villages to have anti-snipe protection, so that I cannot immediately lose them to counter-attacks.

#### Acceptance Criteria

1. WHEN a village is captured THEN the system SHALL set allegiance to the configured post-capture start value
2. WHEN a village is captured THEN the system SHALL activate an anti-snipe floor for the configured duration
3. WHILE the anti-snipe floor is active, WHEN attackers attempt to reduce allegiance THEN the system SHALL prevent allegiance from dropping below the floor value
4. WHEN the anti-snipe period expires THEN the system SHALL allow normal allegiance reduction
5. WHEN a village is captured THEN the system SHALL set a capture cooldown preventing immediate recapture

### Requirement 6

**User Story:** As a game administrator, I want to prevent conquest abuse through protection rules and limits, so that the system remains fair and balanced.

#### Acceptance Criteria

1. WHEN an attacker targets a protected or beginner player THEN the system SHALL block conquest and return ERR_PROTECTED
2. WHEN an attacker targets a village in a safe zone THEN the system SHALL block conquest and return ERR_SAFE_ZONE
3. WHEN a player exceeds the per-account village limit THEN the system SHALL block further captures and return ERR_VILLAGE_CAP
4. WHEN repeated captures occur between the same pair of accounts THEN the system SHALL flag the activity for review and apply diminishing returns
5. WHERE tribe-internal transfers require opt-in, WHEN opt-in is not enabled THEN the system SHALL block conquest and return ERR_HANDOVER_OFF

### Requirement 7

**User Story:** As a player, I want to see detailed conquest reports, so that I can understand what happened during conquest attempts.

#### Acceptance Criteria

1. WHEN a conquest attempt occurs THEN the system SHALL generate a report showing allegiance/control changes
2. WHEN a conquest attempt occurs THEN the system SHALL include surviving Envoy count in the report
3. WHEN a conquest attempt is blocked THEN the system SHALL include the reason code in the report
4. WHEN allegiance regeneration applies between waves THEN the system SHALL show the regeneration amount in reports
5. WHEN wall reductions or other modifiers apply THEN the system SHALL display these factors in the report

### Requirement 8

**User Story:** As an attacker, I want to coordinate multiple Envoy waves with proper timing, so that I can execute effective conquest trains.

#### Acceptance Criteria

1. WHEN an attacker sends multiple Envoy waves to the same target THEN the system SHALL enforce minimum wave spacing per world configuration
2. WHEN wave spacing is violated THEN the system SHALL reject the command or adjust arrival time and return ERR_SPACING
3. WHEN multiple waves arrive in the same tick THEN the system SHALL resolve them in random order to prevent deterministic exploits
4. WHEN waves are resolved THEN the system SHALL log the resolution order for audit purposes
5. WHEN an attacker sends Envoys THEN the system SHALL respect per-village and per-day training caps

### Requirement 9

**User Story:** As a system, I want to handle post-capture state transitions correctly, so that ownership transfers are clean and consistent.

#### Acceptance Criteria

1. WHEN a village is captured THEN the system SHALL transfer ownership to the attacker
2. WHEN a village is captured THEN the system SHALL update diplomacy states for stationed troops
3. WHEN a village is captured THEN the system SHALL transfer remaining resources to the new owner
4. WHEN a village is captured THEN the system SHALL handle allied support according to world configuration
5. WHERE building loss is enabled, WHEN a village is captured THEN the system SHALL optionally reduce one military building by one level

### Requirement 10

**User Story:** As a developer, I want the conquest system to be configurable per world, so that different game modes can have different conquest mechanics.

#### Acceptance Criteria

1. WHEN a world is configured THEN the system SHALL support both allegiance-drop and control-uptime conquest modes
2. WHEN world configuration changes THEN the system SHALL apply the correct regeneration rates, caps, and modifiers
3. WHEN a world enables feature flags THEN the system SHALL respect FEATURE_CONQUEST_UNIT_ENABLED and related toggles
4. WHEN a world sets wave spacing requirements THEN the system SHALL enforce the configured minimum spacing
5. WHEN a world configures Envoy costs and limits THEN the system SHALL apply those values during training and combat
