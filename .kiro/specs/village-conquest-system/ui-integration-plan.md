# UI Integration Plan: Medieval Village Overview Design

## Overview

This document outlines the integration of the Stitch design (project ID: 561542776319018918) into the village conquest system. The design features a medieval-themed interface with parchment aesthetics, ornate borders, and strategic game elements.

## Design Analysis

### Key Visual Elements from Stitch Design

1. **Medieval Aesthetic**
   - Parchment/aged paper background texture
   - Ornate decorative borders and corners
   - Medieval font styling for headers
   - Wax seal and scroll graphics
   - Brown, gold, and dark green color palette

2. **Layout Structure**
   - Header navigation with medieval button styling
   - Centered village title with decorative elements
   - Grid-based building display (2 columns)
   - Side panels for troops and village status
   - Bottom panel for current construction queue

3. **Information Hierarchy**
   - Resources at top (most frequently checked)
   - Buildings in center (primary interaction area)
   - Troops and status on right (reference information)
   - Construction queue at bottom (ongoing activities)

## Integration Strategy

### Phase 1: Core UI Framework (Tasks 15.1-15.5)

**Objective**: Implement the base medieval-themed village overview layout

**Components to Build**:
1. Medieval-themed resource panel with icons and production rates
2. Grid-based buildings section with upgrade interface
3. Troops display panel with unit icons and quantities
4. Village status panel (Loyalty, Morale, Population)
5. Current construction panel with timers and actions

**Technical Approach**:
- Create new CSS file: `css/medieval-theme.css`
- Update `game/game.php` to use new layout structure
- Add parchment background images to `/img/ui/`
- Implement responsive grid system for building display
- Add JavaScript for real-time timer updates

### Phase 2: Conquest System Integration (Tasks 15.6-15.8)

**Objective**: Integrate conquest mechanics into the medieval UI

**Components to Build**:
1. Hall of Banners building page with Envoy training interface
2. Conquest report display styled as medieval scroll
3. Loyalty display (allegiance/control) in Village Status panel
4. Anti-snipe protection indicator with shield icon
5. Capture cooldown indicator with lock icon
6. Control link indicator with progress bar

**Technical Approach**:
- Extend Village Status panel to show conquest metrics
- Create new building page: `buildings/hall_of_banners.php`
- Create conquest report template: `messages/conquest_report.php`
- Add conquest indicators to existing village display
- Implement visual alerts for conquest events

### Phase 3: Styling and Polish (Tasks 15.9-15.10)

**Objective**: Apply medieval theme and add contextual help

**Components to Build**:
1. Complete medieval CSS theme with color palette
2. Parchment/scroll background textures
3. Medieval button and border styling
4. Responsive layout for all screen sizes
5. Conquest tooltips and help system

**Technical Approach**:
- Finalize `css/medieval-theme.css` with all styles
- Add background textures and decorative elements
- Implement tooltip system for conquest mechanics
- Create help documentation for conquest features
- Test responsive design on multiple devices

## File Structure

### New Files to Create

```
css/
  medieval-theme.css          # Main medieval styling
  conquest-ui.css             # Conquest-specific UI styles

img/ui/
  parchment-bg.jpg            # Parchment background texture
  ornate-border-top.png       # Decorative border (top)
  ornate-border-bottom.png    # Decorative border (bottom)
  ornate-corner-tl.png        # Corner decoration (top-left)
  ornate-corner-tr.png        # Corner decoration (top-right)
  ornate-corner-bl.png        # Corner decoration (bottom-left)
  ornate-corner-br.png        # Corner decoration (bottom-right)
  wax-seal.png                # Wax seal graphic
  shield-icon.png             # Anti-snipe protection icon
  lock-icon.png               # Capture cooldown icon
  influence-crest.png         # Influence crest icon

buildings/
  hall_of_banners.php         # Hall of Banners building page

messages/
  conquest_report.php         # Conquest report display

js/
  conquest-ui.js              # Conquest UI interactions
  medieval-timers.js          # Timer updates for construction/conquest
```

### Files to Modify

```
game/game.php                 # Update village overview layout
header.php                    # Add medieval theme CSS links
css/main.css                  # Integrate medieval theme
lib/managers/BuildingManager.php  # Add Hall of Banners support
```

## Implementation Checklist

### Phase 1: Core UI Framework
- [ ] Create `css/medieval-theme.css` with base styles
- [ ] Add parchment background textures to `/img/ui/`
- [ ] Update resource panel with medieval styling
- [ ] Implement grid-based buildings section
- [ ] Create troops display panel
- [ ] Build village status panel
- [ ] Implement current construction panel
- [ ] Test responsive layout on desktop/tablet/mobile

### Phase 2: Conquest System Integration
- [ ] Create Hall of Banners building page
- [ ] Implement Envoy training interface
- [ ] Build conquest report display
- [ ] Add Loyalty display to Village Status panel
- [ ] Implement anti-snipe protection indicator
- [ ] Add capture cooldown indicator
- [ ] Create control link indicator
- [ ] Test conquest UI interactions

### Phase 3: Styling and Polish
- [ ] Finalize medieval color palette
- [ ] Add decorative borders and ornaments
- [ ] Implement medieval button styling
- [ ] Create tooltip system for conquest mechanics
- [ ] Add contextual help documentation
- [ ] Test accessibility (ARIA labels, keyboard navigation)
- [ ] Optimize for performance
- [ ] Cross-browser testing

## Design Specifications

### Color Palette

```css
/* Primary Colors */
--parchment-bg: #F4E8D0;
--dark-brown: #3E2723;
--gold-accent: #D4AF37;
--dark-green: #2E5C3E;

/* Status Colors */
--status-secure: #4CAF50;
--status-contested: #FFC107;
--status-critical: #F44336;
--status-info: #2196F3;

/* Text Colors */
--text-primary: #2C1810;
--text-secondary: #5D4E37;
--text-disabled: #9E9E9E;
```

### Typography

```css
/* Fonts */
--font-header: 'Cinzel', 'Uncial Antiqua', serif;
--font-body: 'Crimson Text', 'Lora', serif;
--font-mono: 'Courier New', monospace;

/* Font Sizes */
--size-page-title: 32px;
--size-section-header: 24px;
--size-building-name: 18px;
--size-body: 14px;
--size-small: 12px;
```

### Layout Grid

```css
/* Building Grid */
.buildings-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

/* Responsive Breakpoints */
@media (max-width: 1199px) {
  .buildings-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 767px) {
  .buildings-grid {
    grid-template-columns: 1fr;
  }
}
```

## Conquest-Specific UI Components

### Loyalty Display (Allegiance/Control)

**Location**: Village Status panel

**Visual Design**:
- Label: "LOYALTY"
- Value: Percentage (0-100%)
- Color-coded based on value:
  - 100-75%: Green (#4CAF50)
  - 74-50%: Yellow (#FFC107)
  - 49-25%: Orange (#FF9800)
  - 24-0%: Red (#F44336)
- Progress bar showing current value
- Tooltip explaining allegiance mechanics

**HTML Structure**:
```html
<div class="status-item loyalty">
  <label>LOYALTY:</label>
  <div class="status-value" data-value="100">
    <span class="percentage">100%</span>
    <div class="progress-bar">
      <div class="progress-fill" style="width: 100%;"></div>
    </div>
  </div>
  <div class="tooltip">
    Loyalty represents your village's allegiance. 
    When it reaches 0%, the village can be captured.
  </div>
</div>
```

### Anti-Snipe Protection Indicator

**Location**: Next to Loyalty display

**Visual Design**:
- Shield icon (gold/bronze color)
- Countdown timer (e.g., "14:32")
- Pulsing glow effect when active
- Tooltip explaining protection

**HTML Structure**:
```html
<div class="anti-snipe-indicator active">
  <img src="/img/ui/shield-icon.png" alt="Protected">
  <span class="timer">14:32</span>
  <div class="tooltip">
    Anti-snipe protection active. 
    Loyalty cannot drop below 10% for 15 minutes after capture.
  </div>
</div>
```

### Control Link Indicator

**Location**: Below Village Status panel (when active)

**Visual Design**:
- Attacker name and tribe
- Control progress bar (0-100)
- Uptime timer (when control = 100)
- Pulsing border when in uptime phase
- Red/orange color scheme (danger)

**HTML Structure**:
```html
<div class="control-link-indicator">
  <div class="attacker-info">
    <strong>Under Attack!</strong>
    <span>Lord_Attacker (Tribe: Warriors)</span>
  </div>
  <div class="control-progress">
    <label>Control:</label>
    <div class="progress-bar">
      <div class="progress-fill" style="width: 75%;"></div>
    </div>
    <span class="percentage">75%</span>
  </div>
  <div class="uptime-timer" style="display: none;">
    <label>Uptime:</label>
    <span class="timer">12:45</span>
    <span class="warning">Village will be captured if control holds!</span>
  </div>
</div>
```

## Testing Plan

### Visual Testing
- [ ] Compare implementation to Stitch design mockup
- [ ] Verify color palette matches design
- [ ] Check typography and font sizes
- [ ] Validate decorative elements placement
- [ ] Test on multiple screen sizes

### Functional Testing
- [ ] Verify resource updates in real-time
- [ ] Test building upgrade interactions
- [ ] Validate construction queue display
- [ ] Check conquest indicator updates
- [ ] Test tooltip functionality

### Accessibility Testing
- [ ] Verify ARIA labels on all interactive elements
- [ ] Test keyboard navigation
- [ ] Check color contrast ratios
- [ ] Validate screen reader compatibility
- [ ] Test with accessibility tools

### Performance Testing
- [ ] Measure page load time
- [ ] Check JavaScript performance
- [ ] Validate CSS optimization
- [ ] Test with slow network connections
- [ ] Monitor memory usage

## Timeline Estimate

**Phase 1: Core UI Framework** - 3-4 days
- Day 1: CSS framework and resource panel
- Day 2: Buildings grid and troops panel
- Day 3: Village status and construction panels
- Day 4: Responsive design and testing

**Phase 2: Conquest System Integration** - 2-3 days
- Day 1: Hall of Banners and Envoy training
- Day 2: Conquest reports and indicators
- Day 3: Testing and refinement

**Phase 3: Styling and Polish** - 2 days
- Day 1: Final styling and decorative elements
- Day 2: Tooltips, help system, and testing

**Total Estimated Time**: 7-9 days

## Success Criteria

1. **Visual Fidelity**: UI matches Stitch design mockup with 95%+ accuracy
2. **Functionality**: All conquest features work correctly in new UI
3. **Performance**: Page load time < 2 seconds, smooth interactions
4. **Accessibility**: WCAG AA compliance, keyboard navigation works
5. **Responsiveness**: Works on desktop, tablet, and mobile devices
6. **User Feedback**: Positive feedback from beta testers

## Next Steps

1. Review this integration plan with the team
2. Gather any additional design assets from Stitch
3. Set up development environment with medieval theme
4. Begin Phase 1 implementation
5. Schedule regular design reviews during development
6. Plan user testing sessions for feedback

## Notes

- The Loyalty display in the Village Status panel directly maps to the allegiance/control system
- Anti-snipe protection and capture cooldown indicators should be prominent but not intrusive
- Conquest reports should feel like receiving a medieval scroll with important news
- All conquest-related UI elements should maintain the medieval aesthetic
- Consider adding sound effects for conquest events (optional enhancement)
