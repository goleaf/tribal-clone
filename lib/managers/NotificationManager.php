<?php

class NotificationManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Add a new notification to the database.
     * @param int $userId User ID
     * @param string $message Notification content
     * @param string $type Notification type (e.g. 'success', 'error', 'info')
     * @return bool True on success, false on failure.
     */
    public function addNotification($userId, $message, $type = 'info', $link = '', $expiresAt = null) {
        if ($expiresAt === null) {
            $expiresAt = time() + (7 * 24 * 60 * 60); // Default 7 days
        }
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at, expires_at) VALUES (?, ?, ?, ?, 0, NOW(), ?)");
        if ($stmt) {
            $stmt->bind_param("isssi", $userId, $message, $type, $link, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Fetch notifications for a user.
     * @param int $userId User ID
     * @param bool $unreadOnly If true, fetch only unread notifications.
     * @param int $limit Maximum number of notifications.
     * @return array List of notifications.
     */
    public function getNotifications($userId, $unreadOnly = false, $limit = 10) {
        // Remove expired notifications before fetching
        $this->cleanExpiredNotifications();

        $query = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        $query .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $notifications = [];
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();
        }
        return $notifications;
    }

    /**
     * Get the unread notification count for a user.
     * @param int $userId User ID
     * @return int Number of unread notifications.
     */
    public function getUnreadNotificationCount(int $userId): int {
        // Optionally clean expired notifications before counting
        // $this->cleanExpiredNotifications();

        $query = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0";

        $stmt = $this->conn->prepare($query);
        $count = 0;
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $count = (int)$row['unread_count'];
            }
            $stmt->close();
        }
        return $count;
    }

    /**
     * Remove expired notifications from the database.
     */
    private function cleanExpiredNotifications() {
        $stmt = $this->conn->prepare("DELETE FROM notifications WHERE expires_at < ?");
        if ($stmt) {
            $currentTime = time();
            $stmt->bind_param("i", $currentTime);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Mark a notification as read.
     * @param int $notificationId Notification ID.
     * @param int $userId User ID (safety check so users only mark their own notifications).
     * @return bool True on success, false on failure.
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $notificationId, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Mark all notifications for a user as read.
     * @param int $userId User ID.
     * @return bool True on success, false on failure.
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}
