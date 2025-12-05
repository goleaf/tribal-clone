# Notification and Alert System Design

## Overview

The Notification and Alert System provides players with multi-channel text-based alerts for all significant game events. The system displays notifications in chronological tables within the game interface, supports optional email notifications, and enables premium SMS alerts for critical events.

This design follows the WAP-style minimalist approach of the game, using server-rendered HTML tables with minimal JavaScript for real-time updates. The system integrates with existing game mechanics (combat, buildings, research, trade, alliances) to emit notifications at key lifecycle points.

**Key Design Principles:**
- **Text-first UI**: All notifications rendered as HTML tables with text prefixes for quick scanning
- **Multi-channel delivery**: In-game (primary), email (optional), SMS (premium critical events)
- **Performance-conscious**: Cached unread counts, batch archival, database-agnostic locking
- **Privacy-safe**: Player-scoped queries, no cross-player data leaks, sanitized external messages
- **Extensible**: Event-driven architecture allows new notification types without core refactoring

## Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Game Event Sources                       │
│  (Combat, Buildings, Research, Trade, Alliance, Conquest)    │
└────────────────────┬────────────────────────────────────────┘
                     │ emit events
                     ▼
┌─────────────────────────────────────────────────────────────┐
│              NotificationManager (Core)                      │
│  - createNotification(player_id, type, data)                │
│  - getNotifications(player_id, filters)                     │
│  - markAsRead(notification_id)                              │
│  - markAllAsRead(player_id)                                 │
│  - getUnreadCount(player_id)                                │
└────────┬────────────────────────────────┬───────────────────┘
         │                                │
         ▼                                ▼
┌──────────────────┐          ┌──────────────────────┐
│ NotificationUI   │          │ NotificationDelivery │
│ - renderTable()  │          │ - sendEmail()        │
│ - renderWidget() │          │ - sendSMS()          │
│ - renderCount()  │          │ - checkPreferences() │
└──────────────────┘          └──────────────────────┘
         │                                │
         ▼                                ▼
┌──────────────────┐          ┌──────────────────────┐
│  Reports Page    │          │  External Services   │
│  Village Widget  │          │  (Email/SMS APIs)    │
└──────────────────┘          └──────────────────────┘
```

### Data Flow

1. **Event Emission**: Game systems call `NotificationManager::createNotification()` after state changes
2. **Preference Check**: Manager queries player preferences to determine if notification should be created
3. **Database Write**: Notification inserted into `notifications` table with transaction safety
4. **Cache Update**: Unread count incremented in player session or cache layer
5. **External Delivery**: If enabled, email/SMS sent asynchronously (non-blocking)
6. **UI Rendering**: Next page load fetches notifications and renders tables/widgets
7. **Read Tracking**: Click handlers mark notifications as read and update counts
8. **Archival**: Cron job moves 60+ day old notifications to archive table



## Components and Interfaces

### NotificationManager

Core service responsible for notification lifecycle management.

**Location**: `lib/managers/NotificationManager.php`

**Public Methods**:

```php
class NotificationManager {
    /**
     * Create a new notification for a player
     * @param int $player_id Target player
     * @param string $type Notification type (attack, build, research, trade, alliance, conquest, support)
     * @param array $data Event-specific data (description, link, metadata)
     * @return int|false Notification ID or false on failure
     */
    public function createNotification(int $player_id, string $type, array $data): int|false;

    /**
     * Get notifications for a player with optional filtering
     * @param int $player_id Target player
     * @param array $filters Optional: type, read_status, limit, offset, date_range
     * @return array List of notification records
     */
    public function getNotifications(int $player_id, array $filters = []): array;

    /**
     * Mark a single notification as read
     * @param int $notification_id Notification to mark
     * @param int $player_id Player making the request (ownership check)
     * @return bool Success status
     */
    public function markAsRead(int $notification_id, int $player_id): bool;

    /**
     * Mark all notifications as read for a player
     * @param int $player_id Target player
     * @return int Number of notifications updated
     */
    public function markAllAsRead(int $player_id): int;

    /**
     * Get unread notification count for a player
     * @param int $player_id Target player
     * @return int Unread count (last 60 days only)
     */
    public function getUnreadCount(int $player_id): int;

    /**
     * Archive old notifications (60+ days)
     * @return array Stats: archived_count, errors
     */
    public function archiveOldNotifications(): array;
}
```

**Design Rationale**:
- Single responsibility: notification CRUD operations only
- Ownership validation in all read/write methods prevents cross-player access
- Filters array allows flexible querying without method explosion
- Returns false/0 on errors rather than exceptions for game flow continuity

### NotificationDelivery

Handles external notification channels (email, SMS).

**Location**: `lib/services/NotificationDelivery.php`

**Public Methods**:

```php
class NotificationDelivery {
    /**
     * Send email notification if player preferences allow
     * @param int $player_id Target player
     * @param string $type Notification type
     * @param array $data Event data (subject, body, link)
     * @return bool Success status
     */
    public function sendEmail(int $player_id, string $type, array $data): bool;

    /**
     * Send SMS notification for critical events (premium only)
     * @param int $player_id Target player
     * @param string $type Notification type
     * @param string $message SMS body (160 chars max)
     * @return bool Success status
     */
    public function sendSMS(int $player_id, string $type, string $message): bool;

    /**
     * Check if player preferences allow notification for event type
     * @param int $player_id Target player
     * @param string $type Notification type
     * @param bool $is_critical Whether event is critical (overrides preferences)
     * @return bool Whether to create notification
     */
    public function shouldNotify(int $player_id, string $type, bool $is_critical): bool;

    /**
     * Validate and store player phone number for SMS
     * @param int $player_id Target player
     * @param string $phone_number Phone in E.164 format
     * @return bool Success status
     */
    public function validateAndStorePhone(int $player_id, string $phone_number): bool;
}
```

**Design Rationale**:
- Separate from NotificationManager to isolate external dependencies
- Non-blocking: email/SMS failures logged but don't block game processing
- Rate limiting built into sendSMS to prevent abuse
- Critical events bypass preferences for player safety

### NotificationUI

Renders notification HTML for various contexts.

**Location**: `lib/utils/NotificationUI.php`

**Public Methods**:

```php
class NotificationUI {
    /**
     * Render full notifications table for Reports page
     * @param array $notifications List of notification records
     * @param int $total_count Total count for pagination
     * @param int $current_page Current page number
     * @return string HTML table markup
     */
    public function renderTable(array $notifications, int $total_count, int $current_page): string;

    /**
     * Render compact Recent Activity widget for village overview
     * @param array $notifications Last 5 notifications (24hr window)
     * @return string HTML widget markup
     */
    public function renderWidget(array $notifications): string;

    /**
     * Render unread count badge for navigation menu
     * @param int $unread_count Number of unread notifications
     * @return string HTML badge markup or empty string if zero
     */
    public function renderCount(int $unread_count): string;

    /**
     * Render notification type prefix with consistent formatting
     * @param string $type Notification type
     * @return string Formatted prefix like "[ATTACK]"
     */
    public function renderPrefix(string $type): string;

    /**
     * Render notification detail page (combat reports, etc.)
     * @param array $notification Full notification record with metadata
     * @return string HTML detail markup
     */
    public function renderDetail(array $notification): string;
}
```

**Design Rationale**:
- Pure rendering logic, no business rules or database access
- Returns HTML strings for server-side composition
- Consistent text-based formatting for WAP compatibility
- Separate methods for different UI contexts (table, widget, badge)



## Data Models

### notifications Table

Primary storage for active notifications (last 60 days).

```sql
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    type VARCHAR(20) NOT NULL,  -- attack, build, research, trade, alliance, conquest, support
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    link VARCHAR(255),  -- Relative URL to detail page
    metadata TEXT,  -- JSON for type-specific data (combat stats, resource amounts, etc.)
    is_read BOOLEAN DEFAULT 0,
    is_critical BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_player_read ON notifications(player_id, is_read, created_at);
CREATE INDEX idx_notifications_player_type ON notifications(player_id, type);
CREATE INDEX idx_notifications_created ON notifications(created_at);
```

**Design Rationale**:
- `type` enum enforces valid notification categories
- `metadata` JSON field allows type-specific data without schema changes
- `is_critical` flag for events that bypass preferences
- Composite index on (player_id, is_read, created_at) optimizes unread count queries
- Cascade delete ensures orphaned notifications removed when player deleted

### notifications_archive Table

Long-term storage for notifications older than 60 days.

```sql
CREATE TABLE notifications_archive (
    id INTEGER PRIMARY KEY,
    player_id INTEGER NOT NULL,
    type VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    link VARCHAR(255),
    metadata TEXT,
    is_read BOOLEAN,
    is_critical BOOLEAN,
    created_at DATETIME,
    read_at DATETIME,
    archived_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_archive_player ON notifications_archive(player_id, created_at);
```

**Design Rationale**:
- Identical schema to `notifications` for simple migration
- `archived_at` timestamp tracks when moved
- Separate table keeps active queries fast
- No foreign key constraint (historical data may reference deleted players)

### notification_preferences Table

Player-specific notification settings.

```sql
CREATE TABLE notification_preferences (
    player_id INTEGER PRIMARY KEY,
    combat_reports BOOLEAN DEFAULT 1,
    building_completions BOOLEAN DEFAULT 1,
    research_completions BOOLEAN DEFAULT 1,
    trade_confirmations BOOLEAN DEFAULT 1,
    alliance_messages BOOLEAN DEFAULT 1,
    conquest_events BOOLEAN DEFAULT 1,
    support_arrivals BOOLEAN DEFAULT 1,
    email_enabled BOOLEAN DEFAULT 0,
    email_address VARCHAR(255),
    sms_enabled BOOLEAN DEFAULT 0,
    sms_phone VARCHAR(20),
    sms_verified BOOLEAN DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Design Rationale**:
- One row per player, defaults to all notifications enabled
- Separate boolean per notification type for granular control
- Email/SMS fields co-located for atomic preference updates
- `sms_verified` prevents unverified numbers from receiving messages

### Notification Type Metadata

Type-specific data stored in `metadata` JSON field:

**attack (Combat Report)**:
```json
{
    "attacker_village": "Village Name (XXX|YYY)",
    "defender_village": "Village Name (XXX|YYY)",
    "outcome": "victory|defeat|draw",
    "attacker_losses": {"spearman": 10, "swordsman": 5},
    "defender_losses": {"spearman": 15, "archer": 8},
    "resources_plundered": {"wood": 500, "clay": 300, "iron": 200},
    "loyalty_damage": 5,
    "wall_level": 10
}
```

**build (Building Completion)**:
```json
{
    "building_name": "Barracks",
    "building_level": 5,
    "village_name": "Village Name (XXX|YYY)"
}
```

**research (Research Completion)**:
```json
{
    "technology_name": "Improved Swords",
    "technology_level": 3,
    "village_name": "Village Name (XXX|YYY)"
}
```

**trade (Trade Activity)**:
```json
{
    "trade_type": "offer_accepted|merchants_arrived|merchants_returned|offer_canceled",
    "partner_name": "Player Name",
    "resources": {"wood": 1000, "clay": 500, "iron": 0},
    "village_from": "Village A (XXX|YYY)",
    "village_to": "Village B (XXX|YYY)"
}
```

**alliance (Alliance Activity)**:
```json
{
    "alliance_name": "The Alliance",
    "activity_type": "mass_mail|diplomacy_change|coordinated_attack|research_complete",
    "sender_name": "Leader Name",
    "subject": "Message Subject"
}
```

**conquest (Village Conquest)**:
```json
{
    "village_name": "Village Name (XXX|YYY)",
    "event_type": "capture_attempt|ownership_changed|loyalty_reduced",
    "attacker_name": "Player Name",
    "loyalty_before": 100,
    "loyalty_after": 75
}
```

**support (Support Arrival)**:
```json
{
    "sender_village": "Village A (XXX|YYY)",
    "receiver_village": "Village B (XXX|YYY)",
    "units": {"spearman": 50, "archer": 30}
}
```



## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

Before defining properties, I've analyzed the requirements to eliminate redundancy:

**Consolidated Properties:**
- Properties 2.3 and 2.4 (count increment/decrement) can be combined into a single "count consistency" property
- Properties 7.3, 7.4, 7.5 (icon rendering) can be combined into "combat report rendering completeness"
- Properties 15.1-15.4 (link generation) can be combined into "notification links are type-appropriate"
- Properties 4.3 and 3.3 (critical event bypass) are redundant—one property covers both in-game and email

**Removed Redundancies:**
- Property about "separate notifications for simultaneous events" (8.5) is covered by "no duplication" property (13.2)
- Email content requirements (4.2) are covered by general "notification content completeness" properties

### Core Properties

**Property 1: Notification ordering consistency**
*For any* set of notifications for a player, when rendered in the Reports table, they should appear in descending chronological order by timestamp.
**Validates: Requirements 1.1**

**Property 2: Notification content completeness**
*For any* notification rendered in a table, the output should contain Type Prefix, Event Description, Timestamp, and Read Status columns.
**Validates: Requirements 1.2**

**Property 3: Type prefix correctness**
*For any* valid notification type (attack, build, research, trade, alliance, conquest, support), the rendered prefix should match the expected format: "[ATTACK]", "[BUILD]", "[RESEARCH]", "[TRADE]", "[ALLIANCE]", "[CONQUEST]", "[SUPPORT]".
**Validates: Requirements 1.3**

**Property 4: Unread visual distinction**
*For any* notification with is_read=false, the rendered table row should contain bold text markup; for is_read=true, it should not.
**Validates: Requirements 1.4**

**Property 5: Mark-as-read updates database**
*For any* notification, calling markAsRead should result in is_read=true and read_at being set to the current timestamp in the database.
**Validates: Requirements 1.5**

**Property 6: Unread count badge rendering**
*For any* unread count N > 0, the navigation menu should display "Reports (N)"; for N = 0, it should display "Reports" without a badge.
**Validates: Requirements 2.1, 2.2**

**Property 7: Count consistency with operations**
*For any* player state, creating a new unread notification should increase the unread count by 1, and marking a notification as read should decrease the count by 1.
**Validates: Requirements 2.3, 2.4**

**Property 8: Count respects 60-day window**
*For any* player, the unread count should only include notifications with created_at within the last 60 days.
**Validates: Requirements 2.5**

**Property 9: Preference enforcement**
*For any* non-critical event of type T, if a player has disabled notifications for type T, then no notification should be created for that event.
**Validates: Requirements 3.2**

**Property 10: Critical events bypass preferences**
*For any* critical event (incoming attack, village capture attempt, village ownership change, alliance war declaration), a notification should be created regardless of player preferences.
**Validates: Requirements 3.3, 4.3**

**Property 11: Preference persistence**
*For any* player, saving notification preferences should result in the database reflecting those preferences, and subsequent shouldNotify checks should use the updated values.
**Validates: Requirements 3.5**

**Property 12: Email delivery respects preferences**
*For any* event of type T, if a player has email_enabled=true and type T is enabled in preferences, then sendEmail should be called; otherwise it should not.
**Validates: Requirements 4.1**

**Property 13: Email content completeness**
*For any* email notification, the generated email body should contain event description, timestamp, and a direct hyperlink to the relevant game page.
**Validates: Requirements 4.2**

**Property 14: Email format is plain text**
*For any* generated email, the content should be plain text without HTML tags or attachments.
**Validates: Requirements 4.4**

**Property 15: Email failures don't block game processing**
*For any* email delivery failure, the system should log the error and return control without throwing an exception that blocks game functionality.
**Validates: Requirements 4.5**

**Property 16: SMS requires premium and verification**
*For any* player, SMS notifications should only be sent if the player has premium status AND sms_verified=true.
**Validates: Requirements 5.1, 5.2**

**Property 17: SMS rate limiting**
*For any* player, sending multiple SMS notifications within a short time window (e.g., 5 minutes) should be throttled to prevent abuse.
**Validates: Requirements 5.5**

**Property 18: Recent Activity widget shows last 5**
*For any* player viewing the village overview, the Recent Activity widget should display at most 5 notifications from the last 24 hours, ordered by timestamp descending.
**Validates: Requirements 6.1, 6.5**

**Property 19: Widget format consistency**
*For any* notification in the Recent Activity widget, the rendered format should match "HH:MM - [TYPE] Event description" and contain a hyperlink.
**Validates: Requirements 6.2, 6.3**

**Property 20: Combat creates dual notifications**
*For any* completed battle, the system should create exactly 2 notifications: one for the attacker and one for the defender.
**Validates: Requirements 7.1**

**Property 21: Combat report content completeness**
*For any* combat report notification, the rendered detail should include initial forces, wall bonus, casualties per unit type, resources plundered, loyalty damage, and an outcome icon (victory/defeat/scout).
**Validates: Requirements 7.2, 7.3, 7.4, 7.5**

**Property 22: Building completion notifications**
*For any* building upgrade completion, the system should create a notification containing the building name, new level, and a hyperlink to the village overview.
**Validates: Requirements 8.1, 8.3**

**Property 23: Research completion notifications**
*For any* research project completion, the system should create a notification containing the technology name and a hyperlink to the research page.
**Validates: Requirements 8.2, 8.3**

**Property 24: Completion notifications respect preferences**
*For any* building or research completion, if the player has disabled completion notifications and the event is not critical, then no notification should be created.
**Validates: Requirements 8.4**

**Property 25: Trade creates appropriate notifications**
*For any* trade event (offer accepted, merchants arrived, merchants returned, offer canceled), the system should create notifications for the appropriate players (both parties for accepted, sender for canceled, receiver for arrivals).
**Validates: Requirements 9.1, 9.2, 9.3, 9.5**

**Property 26: Trade notification content completeness**
*For any* trade notification, the content should include trader name, resource amounts, and village coordinates.
**Validates: Requirements 9.4**

**Property 27: Alliance mass notifications**
*For any* alliance event (mass mail, diplomacy change, coordinated attack, research complete), the system should create notifications for all affected members with the "[ALLIANCE]" prefix.
**Validates: Requirements 10.1, 10.2, 10.3, 10.4**

**Property 28: Alliance notification content completeness**
*For any* alliance notification, the content should include sender name, subject, and timestamp.
**Validates: Requirements 10.5**

**Property 29: Archival moves old notifications**
*For any* notification with created_at older than 60 days, running the archival process should move it from the notifications table to the notifications_archive table.
**Validates: Requirements 11.1**

**Property 30: Archival preserves data**
*For any* notification being archived, all fields (id, player_id, type, title, description, link, metadata, is_read, is_critical, created_at, read_at) should be identical in the archive table.
**Validates: Requirements 11.2**

**Property 31: Archived notifications are accessible**
*For any* player, requesting archived notifications should return notifications from the notifications_archive table that belong to that player.
**Validates: Requirements 11.3**

**Property 32: Archival logging**
*For any* archival run, the system should log the number of notifications archived.
**Validates: Requirements 11.5**

**Property 33: Mark all read updates all unread**
*For any* player, calling markAllAsRead should update all notifications with is_read=false to is_read=true, and only for that player.
**Validates: Requirements 12.1, 12.3**

**Property 34: Mark all read resets count**
*For any* player, after calling markAllAsRead, the unread count should be 0.
**Validates: Requirements 12.2**

**Property 35: No duplicate notifications**
*For any* N simultaneous events, the system should create exactly N notifications without duplication.
**Validates: Requirements 13.2**

**Property 36: Notification failures don't block processing**
*For any* notification creation failure, the system should log the error and continue game processing without throwing an exception.
**Validates: Requirements 13.5**

**Property 37: Error messages don't expose sensitive data**
*For any* database error or notification failure, error messages shown to players should not contain sensitive information (SQL queries, stack traces, internal paths).
**Validates: Requirements 14.5**

**Property 38: Notification links are type-appropriate**
*For any* notification of type T, the link field should point to the appropriate page: combat reports for attack, village overview for build, market for trade, alliance board for alliance.
**Validates: Requirements 15.1, 15.2, 15.3, 15.4**

**Property 39: Invalid links trigger error handling**
*For any* notification with an invalid or broken link, clicking it should display an error message and redirect to the Reports page.
**Validates: Requirements 15.5**



## Error Handling

### Database Errors

**Strategy**: Graceful degradation with logging, no player-facing technical details.

**Scenarios**:

1. **Notification Creation Failure**
   - Log error with context (player_id, type, event data)
   - Return false from createNotification
   - Game processing continues (combat resolves, buildings complete, etc.)
   - Player may miss notification but game state remains consistent

2. **Query Timeout or Lock Contention**
   - SQLite: Use BEGIN IMMEDIATE to acquire write lock early
   - MySQL: Use SELECT FOR UPDATE with reasonable timeout
   - Retry once with exponential backoff (100ms)
   - If still fails, log and continue

3. **Foreign Key Violations**
   - Validate player_id exists before creating notification
   - If player deleted mid-operation, skip notification creation
   - Log orphaned notification attempts for debugging

4. **Archive Migration Errors**
   - Run archival in batches (1000 records at a time)
   - If batch fails, log error and continue to next batch
   - Track last successful archive timestamp to resume
   - Alert admin if failure rate exceeds threshold

### External Service Errors

**Strategy**: Non-blocking, retry with backoff, circuit breaker pattern.

**Email Delivery Failures**:
- SMTP connection timeout: Retry once after 5 seconds
- Invalid email address: Log and disable email for that player
- Rate limit exceeded: Queue for later delivery (max 1 hour delay)
- Permanent failure (5xx): Log and alert admin, don't retry

**SMS Delivery Failures**:
- Invalid phone number: Mark sms_verified=false, notify player
- Insufficient credits: Log and alert admin, disable SMS globally
- Rate limit exceeded: Drop message (SMS is for critical events only)
- Network timeout: Retry once after 10 seconds

**Circuit Breaker**:
- Track failure rate per service (email, SMS)
- If failure rate > 50% over 5 minutes, open circuit
- While open, skip external calls and log locally
- Attempt to close circuit after 15 minutes

### Input Validation Errors

**Strategy**: Reject invalid input early, return clear error messages.

**Validation Rules**:

1. **Notification Type**: Must be one of: attack, build, research, trade, alliance, conquest, support
2. **Player ID**: Must exist in users table and be active
3. **Title**: Max 255 characters, no HTML tags
4. **Description**: Max 5000 characters, sanitize HTML
5. **Link**: Must be relative URL starting with `/`, max 255 characters
6. **Metadata**: Must be valid JSON, max 10KB

**Error Responses**:
- Return false from createNotification with error logged
- For AJAX endpoints, return JSON: `{"success": false, "error": "Invalid notification type"}`
- For form submissions, set session flash message and redirect

### Concurrency Errors

**Strategy**: Optimistic locking for reads, pessimistic for writes.

**Mark as Read Race Condition**:
```php
// Use row-level locking to prevent double-read
BEGIN TRANSACTION;
SELECT * FROM notifications WHERE id = ? AND player_id = ? FOR UPDATE;
UPDATE notifications SET is_read = 1, read_at = ? WHERE id = ?;
COMMIT;
```

**Unread Count Race Condition**:
```php
// Use cached count with transactional updates
BEGIN TRANSACTION;
UPDATE notifications SET is_read = 1 WHERE id = ?;
UPDATE player_cache SET unread_count = unread_count - 1 WHERE player_id = ?;
COMMIT;
```

**Duplicate Notification Prevention**:
- Use unique constraint on (player_id, type, event_id) where applicable
- For events without natural deduplication key, use idempotency tokens
- Log duplicate attempts for debugging

### User-Facing Errors

**Strategy**: Clear, actionable messages without technical jargon.

**Error Messages**:

- **Notification not found**: "This notification has been deleted or you don't have permission to view it."
- **Invalid link**: "The page you're trying to access is no longer available. Redirecting to Reports..."
- **Email configuration error**: "Unable to save email settings. Please check your email address and try again."
- **SMS verification failed**: "Phone number verification failed. Please check the number and try again."
- **Archive access error**: "Unable to load archived notifications. Please try again later."

**Error Page Template**:
```html
<div class="error-message">
    <p><strong>Error:</strong> [User-friendly message]</p>
    <p><a href="/messages/reports.php">Return to Reports</a></p>
</div>
```



## Testing Strategy

### Dual Testing Approach

The notification system will use both unit tests and property-based tests to ensure comprehensive coverage:

- **Unit tests** verify specific examples, edge cases, and integration points
- **Property-based tests** verify universal properties that should hold across all inputs
- Together they provide comprehensive coverage: unit tests catch concrete bugs, property tests verify general correctness

### Property-Based Testing

**Framework**: We will use **Pest PHP** with the **Pest Property Testing Plugin** for property-based testing in PHP 8.4+.

**Configuration**: Each property-based test will run a minimum of 100 iterations to ensure adequate coverage of the input space.

**Test Tagging**: Each property-based test will include a comment explicitly referencing the correctness property from this design document using the format:
```php
// Feature: notification-system, Property 1: Notification ordering consistency
```

**Property Test Examples**:

```php
// Feature: notification-system, Property 1: Notification ordering consistency
test('notifications are ordered by timestamp descending', function () {
    $player_id = createTestPlayer();
    $timestamps = [
        '2024-01-01 10:00:00',
        '2024-01-01 12:00:00',
        '2024-01-01 09:00:00',
        '2024-01-01 11:00:00',
    ];
    
    foreach ($timestamps as $ts) {
        createNotification($player_id, 'attack', ['created_at' => $ts]);
    }
    
    $notifications = getNotifications($player_id);
    $rendered_timestamps = array_column($notifications, 'created_at');
    
    expect($rendered_timestamps)->toBe([
        '2024-01-01 12:00:00',
        '2024-01-01 11:00:00',
        '2024-01-01 10:00:00',
        '2024-01-01 09:00:00',
    ]);
})->repeat(100);

// Feature: notification-system, Property 7: Count consistency with operations
test('creating notification increases count by 1', function () {
    $player_id = createTestPlayer();
    $initial_count = getUnreadCount($player_id);
    
    createNotification($player_id, 'build', ['title' => 'Test']);
    
    $new_count = getUnreadCount($player_id);
    expect($new_count)->toBe($initial_count + 1);
})->repeat(100);

// Feature: notification-system, Property 10: Critical events bypass preferences
test('critical events create notifications regardless of preferences', function () {
    $player_id = createTestPlayer();
    disableAllNotificationPreferences($player_id);
    
    $critical_events = ['incoming_attack', 'village_capture', 'ownership_change', 'war_declaration'];
    
    foreach ($critical_events as $event) {
        $result = createNotification($player_id, 'attack', ['event' => $event, 'is_critical' => true]);
        expect($result)->not->toBeFalse();
    }
})->repeat(100);

// Feature: notification-system, Property 20: Combat creates dual notifications
test('battle completion creates exactly 2 notifications', function () {
    $attacker_id = createTestPlayer();
    $defender_id = createTestPlayer();
    
    $initial_attacker_count = countNotifications($attacker_id);
    $initial_defender_count = countNotifications($defender_id);
    
    completeBattle($attacker_id, $defender_id);
    
    $final_attacker_count = countNotifications($attacker_id);
    $final_defender_count = countNotifications($defender_id);
    
    expect($final_attacker_count)->toBe($initial_attacker_count + 1);
    expect($final_defender_count)->toBe($initial_defender_count + 1);
})->repeat(100);

// Feature: notification-system, Property 30: Archival preserves data
test('archived notifications preserve all fields', function () {
    $player_id = createTestPlayer();
    $notification_id = createNotification($player_id, 'trade', [
        'title' => 'Trade Complete',
        'description' => 'Resources delivered',
        'link' => '/market.php',
        'metadata' => json_encode(['wood' => 1000]),
        'is_read' => true,
        'is_critical' => false,
    ]);
    
    $original = getNotificationById($notification_id);
    
    // Simulate 60+ days passing
    updateNotificationTimestamp($notification_id, date('Y-m-d H:i:s', strtotime('-61 days')));
    
    archiveOldNotifications();
    
    $archived = getArchivedNotificationById($notification_id);
    
    expect($archived['id'])->toBe($original['id']);
    expect($archived['player_id'])->toBe($original['player_id']);
    expect($archived['type'])->toBe($original['type']);
    expect($archived['title'])->toBe($original['title']);
    expect($archived['description'])->toBe($original['description']);
    expect($archived['link'])->toBe($original['link']);
    expect($archived['metadata'])->toBe($original['metadata']);
    expect($archived['is_read'])->toBe($original['is_read']);
    expect($archived['is_critical'])->toBe($original['is_critical']);
})->repeat(100);
```

### Unit Testing

**Framework**: Pest PHP with SQLite in-memory database for fast test execution.

**Test Categories**:

1. **Notification Creation Tests**
   - Test each notification type creates correct metadata structure
   - Test preference enforcement for non-critical events
   - Test critical events bypass preferences
   - Test invalid input rejection

2. **Notification Retrieval Tests**
   - Test filtering by type, read status, date range
   - Test pagination works correctly
   - Test ownership isolation (player A can't see player B's notifications)
   - Test 60-day window enforcement

3. **Read Status Tests**
   - Test marking single notification as read
   - Test marking all as read
   - Test unread count updates correctly
   - Test read_at timestamp is set

4. **Rendering Tests**
   - Test table rendering includes all required columns
   - Test widget rendering shows last 5 notifications
   - Test count badge rendering (with and without count)
   - Test prefix formatting for each type
   - Test bold text for unread notifications

5. **External Delivery Tests**
   - Test email sending respects preferences
   - Test SMS requires premium and verification
   - Test rate limiting prevents spam
   - Test failures don't block game processing

6. **Archival Tests**
   - Test notifications older than 60 days are moved
   - Test archived notifications are accessible
   - Test archival preserves all data
   - Test batch processing works correctly

7. **Integration Tests**
   - Test combat completion creates notifications for both players
   - Test building completion creates notification with correct link
   - Test trade acceptance creates notifications for both parties
   - Test alliance mass mail creates notifications for all members

### Test Data Generators

**Smart Generators** for property-based testing:

```php
// Generate valid notification types
function generateNotificationType(): string {
    return Arr::random(['attack', 'build', 'research', 'trade', 'alliance', 'conquest', 'support']);
}

// Generate valid notification data
function generateNotificationData(string $type): array {
    return match($type) {
        'attack' => [
            'title' => 'Combat Report',
            'description' => 'Battle at Village (' . rand(100, 999) . '|' . rand(100, 999) . ')',
            'link' => '/messages/reports.php?id=' . rand(1, 1000),
            'metadata' => json_encode([
                'outcome' => Arr::random(['victory', 'defeat', 'draw']),
                'attacker_losses' => ['spearman' => rand(0, 100)],
                'defender_losses' => ['spearman' => rand(0, 100)],
            ]),
        ],
        'build' => [
            'title' => 'Building Complete',
            'description' => 'Barracks upgraded to level ' . rand(1, 30),
            'link' => '/game/game.php',
            'metadata' => json_encode([
                'building_name' => 'Barracks',
                'building_level' => rand(1, 30),
            ]),
        ],
        // ... other types
    };
}

// Generate timestamps within valid ranges
function generateTimestamp(int $days_ago_min = 0, int $days_ago_max = 60): string {
    $days_ago = rand($days_ago_min, $days_ago_max);
    return date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
}
```

### Manual Testing Checklist

Some aspects require manual verification:

1. **Email Delivery**
   - Verify emails arrive in inbox (not spam)
   - Verify plain text formatting is readable
   - Verify links in emails work correctly

2. **SMS Delivery**
   - Verify SMS messages arrive on real phone
   - Verify message content is clear and actionable
   - Verify rate limiting prevents spam

3. **UI Rendering**
   - Verify table layout is readable on mobile devices
   - Verify bold text for unread notifications is visible
   - Verify widget fits in village overview without overflow
   - Verify navigation badge updates in real-time

4. **Performance**
   - Verify Reports page loads in < 500ms with 1000 notifications
   - Verify unread count query executes in < 50ms
   - Verify archival process completes in < 5 seconds for 10,000 notifications

5. **Cross-Browser**
   - Test on Chrome, Firefox, Safari, Edge
   - Test on mobile browsers (iOS Safari, Chrome Mobile)
   - Verify meta-refresh works correctly



## Implementation Notes

### Database Considerations

**SQLite vs MySQL Differences**:

1. **Transaction Isolation**
   - SQLite: Use `BEGIN IMMEDIATE` to acquire write lock early and prevent lock escalation
   - MySQL: Use `START TRANSACTION` with `SELECT FOR UPDATE` for row-level locking

2. **Date Functions**
   - SQLite: Use `datetime('now', '-60 days')` for date arithmetic
   - MySQL: Use `DATE_SUB(NOW(), INTERVAL 60 DAY)`

3. **JSON Support**
   - SQLite 3.38+: Native JSON functions available
   - MySQL 5.7+: Native JSON type and functions
   - Fallback: Store as TEXT and parse in PHP

4. **Auto-increment Behavior**
   - SQLite: `AUTOINCREMENT` keyword required for stable IDs
   - MySQL: `AUTO_INCREMENT` is default behavior

**Migration Strategy**:
- Create database-agnostic migration using PDO
- Detect database type at runtime: `$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)`
- Use conditional SQL for database-specific features

### Performance Optimizations

**Caching Strategy**:

1. **Unread Count Cache**
   - Store in player session: `$_SESSION['unread_count']`
   - Update transactionally when notifications created/read
   - Invalidate on logout or session timeout
   - Fallback to database query if cache miss

2. **Recent Notifications Cache**
   - Cache last 5 notifications per player in Redis/Memcached (if available)
   - TTL: 5 minutes
   - Invalidate on new notification creation
   - Fallback to database query if cache unavailable

3. **Notification Preferences Cache**
   - Load once per session and store in `$_SESSION['notification_prefs']`
   - Invalidate when preferences updated
   - Reduces database queries for every notification check

**Query Optimization**:

1. **Composite Indexes**
   - `(player_id, is_read, created_at)` for unread count queries
   - `(player_id, type)` for filtered notification lists
   - `(created_at)` for archival queries

2. **Pagination**
   - Use LIMIT/OFFSET for Reports page
   - Default page size: 50 notifications
   - Include total count for pagination controls

3. **Batch Operations**
   - Archive in batches of 1000 records
   - Mark all as read in single UPDATE statement
   - Bulk insert for alliance mass notifications

**Database Connection Pooling**:
- Reuse PDO connection across notification operations
- Close connection only at end of request
- Use persistent connections for high-traffic scenarios

### Security Considerations

**SQL Injection Prevention**:
- Use prepared statements for ALL queries
- Never concatenate user input into SQL
- Validate notification IDs are integers before querying

**Cross-Player Access Prevention**:
- Always include `player_id` in WHERE clause for reads
- Validate ownership before marking as read
- Use session player_id, never trust client input

**XSS Prevention**:
- Sanitize notification titles and descriptions before storage
- Use `htmlspecialchars()` when rendering to HTML
- Strip HTML tags from user-generated content

**CSRF Protection**:
- Require CSRF token for mark-as-read actions
- Validate token on all POST requests
- Use SameSite cookie attribute

**Rate Limiting**:
- Limit notification creation to 100 per player per minute
- Limit mark-as-read actions to 1000 per player per minute
- Limit email sending to 10 per player per hour
- Limit SMS sending to 5 per player per hour

### Integration Points

**Combat System Integration**:
```php
// In combat resolution code
$battle_result = resolveBattle($attacker_id, $defender_id);

$notification_manager = new NotificationManager($pdo);
$notification_manager->createNotification($attacker_id, 'attack', [
    'title' => 'Combat Report',
    'description' => "Battle at {$defender_village_name}",
    'link' => "/messages/reports.php?id={$battle_result['report_id']}",
    'metadata' => json_encode($battle_result),
    'is_critical' => true,
]);
$notification_manager->createNotification($defender_id, 'attack', [
    'title' => 'Your village was attacked!',
    'description' => "Attack from {$attacker_village_name}",
    'link' => "/messages/reports.php?id={$battle_result['report_id']}",
    'metadata' => json_encode($battle_result),
    'is_critical' => true,
]);
```

**Building Queue Integration**:
```php
// In building completion processor
$building_complete = processBuildingQueue($village_id);

if ($building_complete) {
    $notification_manager->createNotification($player_id, 'build', [
        'title' => 'Building Complete',
        'description' => "{$building_name} upgraded to level {$new_level}",
        'link' => '/game/game.php',
        'metadata' => json_encode([
            'building_name' => $building_name,
            'building_level' => $new_level,
            'village_id' => $village_id,
        ]),
    ]);
}
```

**Trade System Integration**:
```php
// In trade offer acceptance
$trade_accepted = acceptTradeOffer($offer_id, $accepter_id);

if ($trade_accepted) {
    $notification_manager->createNotification($offer['creator_id'], 'trade', [
        'title' => 'Trade Offer Accepted',
        'description' => "{$accepter_name} accepted your trade offer",
        'link' => '/market.php',
        'metadata' => json_encode($offer),
    ]);
    $notification_manager->createNotification($accepter_id, 'trade', [
        'title' => 'Trade Offer Accepted',
        'description' => "You accepted a trade offer from {$creator_name}",
        'link' => '/market.php',
        'metadata' => json_encode($offer),
    ]);
}
```

**Alliance System Integration**:
```php
// In alliance mass mail
$alliance_members = getAllianceMembers($alliance_id);

foreach ($alliance_members as $member) {
    $notification_manager->createNotification($member['player_id'], 'alliance', [
        'title' => 'Alliance Message',
        'description' => $subject,
        'link' => '/player/tribe.php?view=forum',
        'metadata' => json_encode([
            'sender_name' => $sender_name,
            'subject' => $subject,
            'alliance_name' => $alliance_name,
        ]),
    ]);
}
```

### Cron Job Setup

**Archival Job** (`jobs/archive_notifications.php`):
```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../init.php';

$notification_manager = new NotificationManager($pdo);
$result = $notification_manager->archiveOldNotifications();

echo "Archived {$result['archived_count']} notifications\n";
if ($result['errors'] > 0) {
    echo "Errors: {$result['errors']}\n";
}
```

**Crontab Entry**:
```bash
# Run archival daily at 3 AM
0 3 * * * /usr/bin/php /path/to/game/jobs/archive_notifications.php >> /path/to/game/logs/archive.log 2>&1
```

### Monitoring and Logging

**Metrics to Track**:
- Notifications created per minute (by type)
- Unread notification count distribution (percentiles)
- Email delivery success rate
- SMS delivery success rate
- Archival job duration and record count
- Database query performance (slow query log)

**Log Levels**:
- **ERROR**: Notification creation failures, email/SMS delivery failures, database errors
- **WARNING**: Rate limit exceeded, invalid input, missing preferences
- **INFO**: Archival job completion, bulk operations, preference updates
- **DEBUG**: Individual notification creation, query execution times

**Log Format**:
```
[2024-01-15 10:30:45] [ERROR] [NotificationManager] Failed to create notification for player 123: Database connection lost
[2024-01-15 10:30:46] [WARNING] [NotificationDelivery] Email delivery failed for player 456: SMTP timeout
[2024-01-15 03:00:00] [INFO] [ArchivalJob] Archived 5432 notifications in 3.2 seconds
```

### Rollout Plan

**Phase 1: Core Notification System (Week 1)**
- Implement NotificationManager with database tables
- Implement basic notification creation and retrieval
- Add Reports page with table rendering
- Add unread count badge to navigation

**Phase 2: UI Integration (Week 2)**
- Add Recent Activity widget to village overview
- Implement mark-as-read functionality
- Add notification preferences page
- Integrate with existing combat system

**Phase 3: External Delivery (Week 3)**
- Implement email notification delivery
- Implement SMS notification delivery (premium)
- Add phone number verification flow
- Implement rate limiting

**Phase 4: Optimization and Polish (Week 4)**
- Implement caching layer
- Add archival cron job
- Performance testing and optimization
- Bug fixes and edge case handling

**Phase 5: Full Integration (Week 5)**
- Integrate with building queue system
- Integrate with research system
- Integrate with trade system
- Integrate with alliance system

**Phase 6: Monitoring and Refinement (Week 6)**
- Set up monitoring and alerting
- Gather user feedback
- Tune notification preferences defaults
- Optimize query performance based on real usage

