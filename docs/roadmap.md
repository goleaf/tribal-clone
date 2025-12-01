# Roadmap

## 3. Combat depth

Focus on tightening combat feedback and counterplay while keeping the TW feel.

- **Scout/spy clarity**: Separate scouting (army + resources) from spying (research + buildings); gate intel tiers behind research; add counter-scout bonuses from watchtower/church.
- **Richer battle reports**: Show wall damage rolls, ram/catapult effectiveness, surviving morale, loot vs. capacity, and surviving scouts; keep compact HTML for mobile.
- **Movement queues on map**: Render outgoing/incoming armies on the map with travel ETA badges; allow cancel/retreat where rules permit; integrate with notifications.
- **Wall/ram/catapult tuning**: Revisit wall effective level curve, ram suppression vs. destruction, and catapult targeting odds; ship the new curve with test cases and a balance note.
- **Testing**: Unit tests for combat calculations (morale, wall reduction, ram/catapult damage) and report generation to prevent regressions.

Each subtask should land with database migrations (if needed), updated docs, and a short changelog entry.
