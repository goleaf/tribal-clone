# Alliance System Hooks

## 16. before_alliance_invite

**Trigger Point:** Before sending alliance invitation to player

**Parameters:**
- `int $alliance_id` - Alliance sending invite
- `int $inviter_user_id` - User sending the invite
- `int $invitee_user_id` - User being invited
- `string $invitation_message` - Optional message included

**Return Value:** `bool|WP_Error` - True if valid, error object if invalid

**Use Cases:**
1. Validate inviter has permission to invite
2. Check if invitee already has pending invitations
3. Enforce alliance member limits
4. Prevent spam invitations (rate limiting)
5. Validate invitee meets alliance requirements

**Example Implementation:**
```php
add_filter('before_alliance_invite', function($valid, $alliance_id, $inviter_user_id, $invitee_user_id) {
    // Check inviter permission
    $inviter_rank = get_alliance_member_rank($alliance_id, $inviter_user_id);
    if (!in_array($inviter_rank, ['founder', 'leader', 'co-leader'])) {
        return new WP_Error('insufficient_permission', 
            'Only alliance leaders can send invitations.');
    }
    
    // Check member limit
    $current_members = count_alliance_members($alliance_id);
    $member_limit = get_alliance_member_limit($alliance_id);
    
    if ($current_members >= $member_limit) {
        return new WP_Error('alliance_full', 
            'Alliance has reached maximum member capacity.');
    }
    
    // Check if invitee already in an alliance
    $invitee_alliance = get_user_alliance($invitee_user_id);
    if ($invitee_alliance) {
        return new WP_Error('already_in_alliance', 'Player is already in an alliance.');
    }
    
    // Rate limiting: max 10 invites per hour per alliance
    $recent_invites = count_alliance_invites($alliance_id, 3600);
    if ($recent_invites >= 10) {
        return new WP_Error('rate_limit', 
            'Alliance has sent too many invitations recently. Try again later.');
    }
    
    return true;
}, 10);
```

---

## 17. after_alliance_member_join

**Trigger Point:** After a player joins an alliance

**Parameters:**
- `int $alliance_id` - Alliance joined
- `int $user_id` - User who joined
- `string $join_method` - Method: invite/application/founder
- `int $join_time` - Unix timestamp of join

**Return Value:** `void` (action hook)

**Use Cases:**
1. Send welcome notifications to new member and alliance
2. Update alliance statistics and rankings
3. Grant alliance-specific buffs or bonuses
4. Track recruitment metrics for alliance leaders
5. Trigger achievement for joining first alliance


**Example Implementation:**
```php
add_action('after_alliance_member_join', function($alliance_id, $user_id, $join_method, $join_time) {
    // Notify new member
    create_notification($user_id, 'alliance_joined', [
        'alliance_id' => $alliance_id,
        'alliance_name' => get_alliance_name($alliance_id),
        'timestamp' => $join_time
    ]);
    
    // Notify alliance leaders
    $leaders = get_alliance_leaders($alliance_id);
    foreach ($leaders as $leader_id) {
        create_notification($leader_id, 'new_member', [
            'user_id' => $user_id,
            'username' => get_username($user_id),
            'join_method' => $join_method,
            'timestamp' => $join_time
        ]);
    }
    
    // Update alliance member count
    increment_alliance_stat($alliance_id, 'total_members');
    
    // Check achievement: join first alliance
    if (!has_achievement($user_id, 'alliance_member')) {
        grant_achievement($user_id, 'alliance_member');
    }
    
    // Apply alliance territory bonuses to user's villages
    apply_alliance_bonuses($user_id, $alliance_id);
}, 10);
```

---

## 18. calculate_alliance_ranking

**Trigger Point:** When recalculating alliance ranking points

**Parameters:**
- `int $alliance_id` - Alliance being calculated
- `int $base_points` - Sum of all member village points
- `int $member_count` - Number of alliance members
- `array $war_stats` - Alliance war statistics

**Return Value:** `int` - Final alliance ranking points

**Use Cases:**
1. Apply bonuses for territory control
2. Add points for successful wars and conquests
3. Implement activity bonuses for active alliances
4. Apply penalties for inactive members
5. Add prestige points from special achievements

**Example Implementation:**
```php
add_filter('calculate_alliance_ranking', function($base_points, $alliance_id, $member_count, $war_stats) {
    $points = $base_points;
    
    // Territory control bonus: +2% per controlled region
    $controlled_regions = count_controlled_regions($alliance_id);
    $points = floor($points * (1 + ($controlled_regions * 0.02)));
    
    // War victory bonus: +500 points per won war
    if (isset($war_stats['wars_won'])) {
        $points += $war_stats['wars_won'] * 500;
    }
    
    // Activity bonus: +5% if >80% members active in last 7 days
    $active_members = count_active_members($alliance_id, 604800); // 7 days
    $activity_rate = $active_members / $member_count;
    
    if ($activity_rate >= 0.80) {
        $points = floor($points * 1.05);
    }
    
    // Size penalty: -2% per member over 50 (encourages quality over quantity)
    if ($member_count > 50) {
        $penalty = ($member_count - 50) * 0.02;
        $points = floor($points * (1 - min(0.20, $penalty))); // Max 20% penalty
    }
    
    return $points;
}, 10);
```
