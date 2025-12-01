/**
 * Toast notification handling and dropdown rendering.
 */

class ToastManager {
    constructor() {
        this.toastContainer = this.getOrCreateToastContainer();
    }

    getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Show a toast notification.
     * @param {string} message Text to display.
     * @param {string} type Notification type (success, error, info, warning).
     * @param {number} duration Display time in ms.
     */
    showToast(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.classList.add('game-toast', type);
        toast.textContent = message;

        this.toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.add('visible');
        }, 100);

        // Hide and remove after duration
        setTimeout(() => {
            toast.classList.remove('visible');
            toast.addEventListener(
                'transitionend',
                () => toast.remove(),
                { once: true }
            );
        }, duration);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.toastManager = new ToastManager();

    // Display messages passed from PHP
    if (window.gameMessages && Array.isArray(window.gameMessages)) {
        window.gameMessages.forEach(msg => {
            window.toastManager.showToast(msg.message, msg.type);
        });
    }

    // Notifications dropdown handling
    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationCountBadge = document.getElementById('notification-count');
    const notificationsList = document.getElementById('notifications-list');
    const markAllReadBtn = document.getElementById('mark-all-read');

    if (notificationsToggle && notificationsDropdown) {
        notificationsToggle.addEventListener('click', e => {
            e.preventDefault();
            notificationsDropdown.classList.toggle('show');
            if (notificationsDropdown.classList.contains('show')) {
                fetchNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', e => {
            if (
                !notificationsToggle.contains(e.target) &&
                !notificationsDropdown.contains(e.target)
            ) {
                notificationsDropdown.classList.remove('show');
            }
        });
    }

    // Fetch and render notifications
    async function fetchNotifications() {
        try {
            const response = await fetch('ajax/get_notifications.php?unreadOnly=true&limit=5');
            const data = await response.json();

            if (data.status === 'success') {
                renderNotifications(data.data.notifications, data.data.unread_count);
            } else {
                console.error('Error fetching notifications:', data.message);
                toastManager.showToast('Error fetching notifications.', 'error');
            }
        } catch (error) {
            console.error('Notifications AJAX error:', error);
            toastManager.showToast('Server communication error.', 'error');
        }
    }

    // Render notifications in the dropdown
    function renderNotifications(notifications, unreadCount) {
        if (!notificationsList) return;

        notificationsList.innerHTML = '';

        if (notifications.length === 0) {
            notificationsList.innerHTML = '<div class="no-notifications">No new notifications</div>';
        } else {
            const ul = document.createElement('ul');
            ul.classList.add('notifications-list-items');
            notifications.forEach(notification => {
                const li = document.createElement('li');
                li.classList.add('notification-item', `notification-${notification.type}`);
                li.dataset.id = notification.id;

                const iconClass =
                    notification.type === 'success'
                        ? 'fa-check-circle'
                        : notification.type === 'error'
                        ? 'fa-exclamation-circle'
                        : notification.type === 'info'
                        ? 'fa-info-circle'
                        : 'fa-bell';

                li.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${relativeTime(new Date(notification.created_at).getTime() / 1000)}</div>
                    </div>
                    <button class="mark-read-btn" data-id="${notification.id}" title="Mark as read">
                        <i class="fas fa-check"></i>
                    </button>
                `;
                ul.appendChild(li);
            });
            notificationsList.appendChild(ul);
        }

        // Update unread badge
        if (notificationCountBadge) {
            if (unreadCount > 0) {
                notificationCountBadge.textContent = unreadCount;
                notificationCountBadge.style.display = 'block';
            } else {
                notificationCountBadge.style.display = 'none';
            }
        }
    }

    // Mark a single notification as read
    if (notificationsList) {
        notificationsList.addEventListener('click', async e => {
            if (e.target.closest('.mark-read-btn')) {
                const button = e.target.closest('.mark-read-btn');
                const notificationId = button.dataset.id;
                try {
                    const response = await fetch('ajax/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `id=${notificationId}`
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        button.closest('.notification-item')?.remove();
                        fetchNotifications();
                    }
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                    toastManager.showToast('Error marking notification as read.', 'error');
                }
            }
        });
    }

    // Mark all as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async e => {
            e.preventDefault();
            try {
                const response = await fetch('ajax/mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                if (data.status === 'success') {
                    notificationsList.innerHTML = '<div class="no-notifications">No new notifications</div>';
                    if (notificationCountBadge) {
                        notificationCountBadge.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
                toastManager.showToast('Error marking notifications as read.', 'error');
            }
        });
    }
});
