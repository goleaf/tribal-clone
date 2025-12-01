# Messaging System Fix

## Issues Fixed

### 1. Missing `sendMessage()` Method in MessageManager
**Problem:** The `send_message.php` file was calling `$messageManager->sendMessage()` but this method didn't exist in the MessageManager class.

**Solution:** Added the `sendMessage()` method to `lib/managers/MessageManager.php`:
```php
public function sendMessage(int $senderId, int $receiverId, string $subject, string $body): array
```

This method:
- Validates inputs (subject and body required)
- Prevents sending messages to self
- Inserts message into database
- Returns success status and message ID

### 2. Incorrect Header Path in send_message.php
**Problem:** The file was trying to include `header.php` from the current directory instead of the parent directory.

**Solution:** Changed:
```php
require 'header.php';  // WRONG
```
To:
```php
require '../header.php';  // CORRECT
```

Also added VillageManager initialization for the header section.

## Files Modified

1. **lib/managers/MessageManager.php**
   - Added `sendMessage()` method

2. **messages/send_message.php**
   - Fixed header.php path
   - Fixed footer.php path
   - Added VillageManager initialization

## Testing

### Test Notifications
```bash
# Visit in browser:
http://localhost:8000/ajax/get_notifications.php?unreadOnly=true&limit=5
```

Should return JSON with notifications.

### Test New Message
1. Go to: `http://localhost:8000/messages/messages.php`
2. Click "Write a message" button
3. Fill in recipient, subject, and body
4. Click "Send message"
5. Should redirect to sent messages tab

## Expected Behavior

### Notifications Endpoint
- Returns JSON with status, notifications array, and unread count
- Filters by unreadOnly parameter
- Limits results based on limit parameter

### Send Message
- Validates all fields are filled
- Checks recipient exists
- Prevents sending to self
- Creates message in database
- Sends notification to recipient
- Redirects to sent messages

## Database Schema Required

### messages table
```sql
CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    sent_at DATETIME NOT NULL,
    is_read INTEGER DEFAULT 0,
    is_archived INTEGER DEFAULT 0,
    is_sender_deleted INTEGER DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);
```

### notifications table
```sql
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    link TEXT DEFAULT '',
    is_read INTEGER DEFAULT 0,
    created_at DATETIME NOT NULL,
    expires_at INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Status

✅ All syntax errors fixed
✅ Missing methods added
✅ Path issues resolved
✅ Ready for testing

## Next Steps

1. Test notifications endpoint in browser
2. Test sending a new message
3. Verify message appears in recipient's inbox
4. Check notification is created
5. Test reply functionality
