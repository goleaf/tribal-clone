# Requirements Document

## Introduction

The Notification and Alert System provides players with multi-channel text-based alerts for all significant game events. The system displays notifications in chronological tables within the game interface, supports optional email notifications, and enables premium SMS alerts for critical events. This system is foundational to player engagement, ensuring players stay informed about combat outcomes, construction completions, research breakthroughs, trade activities, and alliance communications without requiring constant game monitoring.

## Glossary

- **Notification**: An in-game alert about a game event displayed in the Reports section
- **Notification Type**: Category of event (attack, build, research, trade, alliance, conquest, support)
- **Notification Prefix**: Text identifier like "[ATTACK]", "[BUILD]", "[RESEARCH]" for quick scanning
- **Read Status**: Boolean indicating whether a player has viewed a notification
- **Notification Count**: Number of unread notifications displayed in navigation menu
- **Email Notification**: Optional external alert sent to player's registered email address
- **SMS Notification**: Premium feature sending critical alerts to player's phone number
- **Recent Activity Widget**: Compact display of last 5 notifications on village overview
- **Notification Preferences**: Player-configurable settings for which events trigger notifications
- **Archive**: Storage for notifications older than 60 days moved to separate table
- **Critical Event**: High-priority event that always notifies regardless of preferences (incoming attacks, village capture)

## Requirements

### Requirement 1

**User Story:** As a player, I want to view all game events in a chronological Reports section, so that I can review what happened while I was offline and stay informed about my villages.

#### Acceptance Criteria

1. WHEN a player accesses the Reports section THEN the System SHALL display all notifications in a chronological table ordered by timestamp descending
2. WHEN displaying notifications THEN the System SHALL show columns for Type Prefix, Event Description, Timestamp, and Read Status
3. WHEN displaying notification type prefixes THEN the System SHALL use text identifiers: "[ATTACK]", "[BUILD]", "[RESEARCH]", "[TRADE]", "[ALLIANCE]", "[CONQUEST]", "[SUPPORT]"
4. WHEN a notification is unread THEN the System SHALL render the table row in bold text
5. WHEN a player clicks a notification THEN the System SHALL mark it as read and navigate to the relevant game page

### Requirement 2

**User Story:** As a player, I want to see an unread notification count in the navigation menu, so that I know when new events require my attention without constantly checking the Reports page.

#### Acceptance Criteria

1. WHEN the navigation menu is rendered THEN the System SHALL display "Reports (N)" where N is the count of unread notifications
2. WHEN a player has zero unread notifications THEN the System SHALL display "Reports" without a count badge
3. WHEN a notification is marked as read THEN the System SHALL decrement the unread count immediately
4. WHEN a new notification is created THEN the System SHALL increment the unread count for the affected player
5. WHEN calculating unread count THEN the System SHALL only include notifications from the last 60 days

### Requirement 3

**User Story:** As a player, I want to configure which events trigger notifications, so that I can reduce noise and focus on events that matter to my playstyle.

#### Acceptance Criteria

1. WHEN a player accesses notification settings THEN the System SHALL display checkboxes for each notification type: Combat Reports, Building Completions, Research Completions, Trade Confirmations, Alliance Messages, Conquest Events, Support Arrivals
2. WHEN a player disables a notification type THEN the System SHALL not create in-game notifications for that event type
3. WHEN a critical event occurs THEN the System SHALL create a notification regardless of player preferences
4. WHEN defining critical events THEN the System SHALL include: incoming attacks, village capture attempts, village ownership changes, alliance war declarations
5. WHEN a player saves notification preferences THEN the System SHALL persist settings to the database and apply them immediately

### Requirement 4

**User Story:** As a player, I want optional email notifications for important events, so that I can respond to threats even when not actively playing the game.

#### Acceptance Criteria

1. WHEN a player enables email notifications in settings THEN the System SHALL send plain text emails for configured event types
2. WHEN sending email notifications THEN the System SHALL include event description, timestamp, and direct hyperlink to the relevant game page
3. WHEN a critical event occurs THEN the System SHALL send an email notification regardless of player preferences
4. WHEN sending emails THEN the System SHALL use plain text format without HTML or attachments
5. WHEN email delivery fails THEN the System SHALL log the error and continue processing without blocking game functionality

### Requirement 5

**User Story:** As a premium player, I want SMS notifications for critical events, so that I can respond immediately to incoming attacks or village captures.

#### Acceptance Criteria

1. WHERE a player has premium status THEN the System SHALL enable SMS notification configuration in settings
2. WHEN a player configures SMS notifications THEN the System SHALL require phone number validation before activation
3. WHEN a critical event occurs for a premium player with SMS enabled THEN the System SHALL send a text message with event summary
4. WHEN defining SMS-eligible events THEN the System SHALL limit to: incoming attacks within 1 hour, village capture attempts, village ownership changes
5. WHEN sending SMS notifications THEN the System SHALL rate-limit to prevent abuse and excessive charges

### Requirement 6

**User Story:** As a player, I want a Recent Activity widget on my village overview, so that I can quickly see the last few events without navigating to the Reports page.

#### Acceptance Criteria

1. WHEN a player views the village overview THEN the System SHALL display a Recent Activity widget showing the last 5 notifications
2. WHEN displaying recent notifications THEN the System SHALL show them in compact format: "HH:MM - [TYPE] Event description"
3. WHEN displaying recent notifications THEN the System SHALL render each as a hyperlink to the full notification detail
4. WHEN a village has no recent notifications THEN the System SHALL display "No recent activity"
5. WHEN calculating recent notifications THEN the System SHALL only include events from the last 24 hours

### Requirement 7

**User Story:** As a player, I want detailed combat reports showing battle outcomes, so that I can analyze what happened and adjust my strategies.

#### Acceptance Criteria

1. WHEN a battle completes THEN the System SHALL create a combat report notification for both attacker and defender
2. WHEN displaying a combat report THEN the System SHALL show initial forces, wall bonus, casualties per unit type, resources plundered, and loyalty damage in text tables
3. WHEN a combat report includes a victory THEN the System SHALL display a 16×16 icon indicating the outcome
4. WHEN a combat report includes a defeat THEN the System SHALL display a 16×16 icon indicating the outcome
5. WHEN a combat report is for a scout mission THEN the System SHALL display a 16×16 icon and include intelligence gathered

### Requirement 8

**User Story:** As a player, I want notifications for building and research completions, so that I know when to queue new construction or train units.

#### Acceptance Criteria

1. WHEN a building upgrade completes THEN the System SHALL create a notification with building name and new level
2. WHEN a research project completes THEN the System SHALL create a notification with technology name
3. WHEN displaying completion notifications THEN the System SHALL include a hyperlink to the relevant building or research page
4. WHEN a player has completion notifications disabled THEN the System SHALL not create these notifications unless they are critical
5. WHEN multiple completions occur simultaneously THEN the System SHALL create separate notifications for each event

### Requirement 9

**User Story:** As a player, I want notifications for trade activities, so that I can track resource exchanges and market transactions.

#### Acceptance Criteria

1. WHEN a trade offer is accepted THEN the System SHALL create notifications for both sender and receiver
2. WHEN merchants arrive with resources THEN the System SHALL create a notification with resource amounts
3. WHEN merchants return from delivery THEN the System SHALL create a notification confirming completion
4. WHEN displaying trade notifications THEN the System SHALL include trader name, resource amounts, and village coordinates
5. WHEN a trade offer is canceled THEN the System SHALL create a notification for the offer creator

### Requirement 10

**User Story:** As a player, I want notifications for alliance activities, so that I can stay informed about member actions, diplomacy changes, and coordinated operations.

#### Acceptance Criteria

1. WHEN an alliance leader sends a mass mail THEN the System SHALL create notifications for all members with "[ALLIANCE]" prefix
2. WHEN alliance diplomacy status changes THEN the System SHALL create notifications for all affected alliance members
3. WHEN a coordinated attack is scheduled THEN the System SHALL create notifications for participating members
4. WHEN alliance research completes THEN the System SHALL create notifications for all members
5. WHEN displaying alliance notifications THEN the System SHALL include sender name, subject, and timestamp

### Requirement 11

**User Story:** As a system administrator, I want old notifications archived automatically, so that the active notifications table remains performant as the player base grows.

#### Acceptance Criteria

1. WHEN notifications are older than 60 days THEN the System SHALL move them to an archive table
2. WHEN archiving notifications THEN the System SHALL preserve all data including read status and timestamps
3. WHEN a player requests archived notifications THEN the System SHALL display them in a separate "Archive" tab
4. WHEN archiving runs THEN the System SHALL use batch operations to minimize database load
5. WHEN archiving completes THEN the System SHALL log the number of notifications archived

### Requirement 12

**User Story:** As a player, I want to mark all notifications as read with one action, so that I can quickly clear my notification count after reviewing events.

#### Acceptance Criteria

1. WHEN a player clicks "Mark All Read" THEN the System SHALL update all unread notifications to read status
2. WHEN marking all as read THEN the System SHALL reset the unread count to zero
3. WHEN marking all as read THEN the System SHALL only affect notifications for the current player
4. WHEN marking all as read THEN the System SHALL use a single database transaction
5. WHEN marking all as read completes THEN the System SHALL redirect to the Reports page with updated display

### Requirement 13

**User Story:** As a developer, I want the notification system to prevent race conditions and duplicate notifications, so that players receive accurate event counts and no duplicate alerts.

#### Acceptance Criteria

1. WHEN creating a notification THEN the System SHALL use database transactions to ensure atomicity
2. WHEN multiple events occur simultaneously THEN the System SHALL create separate notifications without duplication
3. WHEN marking notifications as read THEN the System SHALL use row-level locking to prevent concurrent modification
4. WHEN calculating unread counts THEN the System SHALL use cached values updated transactionally
5. WHEN notification creation fails THEN the System SHALL log the error and continue game processing without blocking

### Requirement 14

**User Story:** As a system administrator, I want the notification system to work with both SQLite and MySQL databases, so that the game can run in different hosting environments.

#### Acceptance Criteria

1. WHEN using SQLite THEN the System SHALL use BEGIN IMMEDIATE for transactions to prevent lock escalation
2. WHEN using MySQL THEN the System SHALL use SELECT FOR UPDATE for row-level locking
3. WHEN detecting the database type THEN the System SHALL adapt locking strategies accordingly
4. WHEN executing queries THEN the System SHALL use prepared statements to prevent SQL injection
5. WHEN handling database errors THEN the System SHALL log errors without exposing sensitive information to players

### Requirement 15

**User Story:** As a player, I want notification hyperlinks to navigate directly to relevant game pages, so that I can quickly act on events without searching for the right location.

#### Acceptance Criteria

1. WHEN a combat notification is clicked THEN the System SHALL navigate to the full combat report detail page
2. WHEN a building completion notification is clicked THEN the System SHALL navigate to the village overview
3. WHEN a trade notification is clicked THEN the System SHALL navigate to the market page
4. WHEN an alliance notification is clicked THEN the System SHALL navigate to the alliance message board
5. WHEN a notification link is invalid THEN the System SHALL display an error message and redirect to the Reports page
