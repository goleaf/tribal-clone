<?php
require '../init.php';

require_once __DIR__ . '/../lib/managers/MessageManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

$messageManager = new MessageManager($conn);

// Handle message tabs
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$validTabs = ['inbox', 'sent', 'archive'];

if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'inbox';
}

// === Pagination ===
$messagesPerPage = 20; // Messages per page
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $messagesPerPage;
$totalMessages = 0;
$totalPages = 1;

// Handle bulk message operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['message_ids'])) {
    $action = $_POST['action'];
    $message_ids = $_POST['message_ids'];

    if (!empty($message_ids) && is_array($message_ids)) {
        // Use MessageManager to perform the bulk action
        $success = $messageManager->performBulkAction($user_id, $action, $message_ids);

        if ($success) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Action completed successfully.',
                    'redirect' => "messages.php?tab={$activeTab}"
                ]);
                exit();
            }
            header("Location: messages.php?tab=$activeTab&action_success=1");
            exit();
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Bulk action failed.',
                ]);
                exit();
            }
            // For non-AJAX fall back to redirect without success flag
            header("Location: messages.php?tab=$activeTab&action_error=1");
            exit();
        }
    }
}

// Fetch messages for the active tab with pagination using MessageManager
$messageData = $messageManager->getUserMessages($user_id, $activeTab, $offset, $messagesPerPage);
$messages = $messageData['messages'];
$totalMessages = $messageData['total'];

$totalPages = ceil($totalMessages / $messagesPerPage); // Calculate total pages based on total messages

// Ensure the current page does not exceed the number of pages
if ($currentPage > $totalPages && $totalPages > 0) {
    // Either redirect or clamp to the last page
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $messagesPerPage;
    // Re-fetch messages for the corrected page if necessary, though getUserMessages handles offset directly
    // $messageData = $messageManager->getUserMessages($user_id, $activeTab, $offset, $messagesPerPage);
    // $messages = $messageData['messages'];
}

// Get message counts via MessageManager
$messageCounts = $messageManager->getMessageCounts($user_id);
$unread_count = $messageCounts['unread'];
$archive_count = $messageCounts['archive'];
$sent_count = $messageCounts['sent'];

$pageTitle = 'Messages';
require '../header.php';
?>

<div id="game-container">
    <!-- Game header with resources -->
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#9993;</span>
            <span>Messages</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
        </div>
    </header>

    <div id="main-content">
        <!-- Sidebar navigation -->
        
        <main>
            <h2>Messages</h2>
            
            <?php if (isset($_GET['action_success'])): ?>
                <div class="success-message">Action completed successfully.</div>
            <?php endif; ?>
            
            <div class="messages-tabs">
                <a href="messages.php?tab=inbox" class="tab <?= $activeTab === 'inbox' ? 'active' : '' ?>">
                    Inbox 
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="messages.php?tab=sent" class="tab <?= $activeTab === 'sent' ? 'active' : '' ?>">
                    Sent
                    <?php if ($sent_count > 0): ?>
                        <span class="badge"><?= $sent_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="messages.php?tab=archive" class="tab <?= $activeTab === 'archive' ? 'active' : '' ?>">
                    Archive
                    <?php if ($archive_count > 0): ?>
                        <span class="badge"><?= $archive_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="messages-toolbar">
                <a href="send_message.php" class="btn btn-primary">
                    <i class="fas fa-pen"></i> Write a message
                </a>
                
                <?php if (!empty($messages)): ?>
                    <form method="post" id="messages-form" action="messages.php?tab=<?= $activeTab ?>">
                        <div class="bulk-actions">
                            <select name="action" id="bulk-action">
                                <option value="">Select an action...</option>
                                <?php if ($activeTab === 'inbox'): ?>
                                    <option value="mark_read">Mark as read</option>
                                    <option value="mark_unread">Mark as unread</option>
                                    <option value="archive">Move to archive</option>
                                <?php elseif ($activeTab === 'archive'): ?>
                                    <option value="unarchive">Restore to inbox</option>
                                <?php endif; ?>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="submit" id="bulk-apply" class="btn btn-secondary" disabled>Apply</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($messages)): ?>
                <div class="messages-container">
                     <div class="messages-list">
                         <?php foreach ($messages as $msg): ?>
                            <div class="message-item <?= ($activeTab !== 'sent' && !$msg['is_read']) ? 'unread' : '' ?>" data-message-id="<?= $msg['id'] ?>">
                                <div class="message-checkbox">
                                    <input type="checkbox" name="message_ids[]" value="<?= $msg['id'] ?>" form="messages-form" class="message-checkbox-input">
                                </div>
                                <div class="message-status">
                                    <?php if ($activeTab !== 'sent' && !$msg['is_read']): ?>
                                        <i class="fas fa-envelope status-icon unread-icon" title="Unread"></i>
                                    <?php else: ?>
                                        <i class="fas fa-envelope-open status-icon read-icon" title="Read"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="message-sender">
                                     <?php if ($activeTab === 'sent'): ?>
                                         <a href="../player/player.php?id=<?= $msg['receiver_id'] ?>" class="player-link">
                                             <?= htmlspecialchars($msg['receiver_username']) ?>
                                         </a>
                                     <?php else: ?>
                                         <a href="../player/player.php?id=<?= $msg['sender_id'] ?>" class="player-link">
                                             <?= htmlspecialchars($msg['sender_username']) ?>
                                         </a>
                                     <?php endif; ?>
                                </div>
                                <div class="message-subject">
                                     <?= htmlspecialchars($msg['subject']) ?>
                                </div>
                                <div class="message-date">
                                     <?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?>
                                </div>
                                <div class="message-actions">
                                     <!-- Actions handled via JS/AJAX or remain as links -->
                                     <button class="action-btn view-message-btn" data-message-id="<?= $msg['id'] ?>" title="Preview">
                                          <i class="fas fa-eye"></i>
                                     </button>
                                     <?php if ($activeTab === 'inbox'): ?>
                                         <a href="send_message.php?reply_to=<?= $msg['id'] ?>" class="action-btn reply-btn" title="Reply">
                                             <i class="fas fa-reply"></i>
                                         </a>
                                     <?php endif; ?>
                                     <button class="action-btn delete-message-btn" data-message-id="<?= $msg['id'] ?>" title="Delete">
                                          <i class="fas fa-trash"></i>
                                     </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                     </div>
                     
                     <!-- Message details section - initially empty -->
                     <div class="message-details" id="message-details">
                          <p>Select a message from the list to view details.</p>
                     </div>

                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $currentPage - 1 ?>" class="page-link">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $currentPage + 1 ?>" class="page-link">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-messages">
                    <p>No messages in this mailbox.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>

<script src="../js/messages.js"></script>

<script>
    // js/messages.js

    document.addEventListener('DOMContentLoaded', function() {
        const messagesList = document.querySelector('.messages-list');
        const messageDetailsArea = document.getElementById('message-details'); // Assuming this div exists in messages.php

        // Embed user_id from PHP
        const currentUserId = <?= json_encode($user_id) ?>;

        if (messagesList && messageDetailsArea) {
            // Event listener for clicking on message items in the list
            messagesList.addEventListener('click', function(event) {
                const messageItem = event.target.closest('.message-item');
                const viewButton = event.target.closest('.view-message-btn');

                // Check if a message item was clicked or the view button within it
                // Ensure the click is not inside the checkbox or action buttons that have their own handlers
                const isCheckboxClick = event.target.classList.contains('message-checkbox-input');
                const isActionButton = event.target.closest('.action-btn'); // Check if any action button was clicked

                if (messageItem && !isCheckboxClick && !isActionButton) {
                     const messageId = messageItem.dataset.messageId;

                    if (messageId) {
                        // Prevent default link behavior if clicked element is a link
                        if (event.target.tagName === 'A') {
                            event.preventDefault();
                        }

                        // Load message details
                        loadMessageDetails(messageId);
                    }
                } else if (viewButton) { // Handle clicks specifically on the view button
                     const messageId = viewButton.dataset.messageId;
                     if (messageId) {
                          event.preventDefault(); // Prevent default button behavior
                          loadMessageDetails(messageId);
                     }
                }
            });

            // Function to load message details via AJAX
            function loadMessageDetails(messageId) {
                // Show a loading indicator (optional)
                messageDetailsArea.innerHTML = '<p>Loading message...</p>';
                messageDetailsArea.classList.add('loading');

                // Fetch message details from view_message.php
                // Pass the current tab to view_message.php for the correct "Back" link and actions
                const urlParams = new URLSearchParams(window.location.search);
                const currentTab = urlParams.get('tab') || 'inbox';
                fetch(`view_message.php?id=${messageId}&tab=${encodeURIComponent(currentTab)}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                    }
                })
                .then(response => {
                    if (!response.ok) {
                         throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    messageDetailsArea.classList.remove('loading');

                    if (data.success) {
                        // Render message details HTML
                        renderMessageDetails(data.messageData);
                        // Mark message as read in the list UI
                        const messageItem = messagesList.querySelector(`.message-item[data-message-id='${messageId}']`);
                        if (messageItem && data.messageData && data.messageData.is_read) {
                             messageItem.classList.remove('unread');
                             const statusIcon = messageItem.querySelector('.status-icon');
                             if(statusIcon) {
                                 statusIcon.classList.remove('fa-envelope', 'unread-icon');
                                 statusIcon.classList.add('fa-envelope-open', 'read-icon');
                                 statusIcon.title = 'Read';
                             }
                        }
                        // TODO: Update unread counts in tabs

                    } else {
                        // Handle error loading details
                        messageDetailsArea.innerHTML = `<p class="error-message">${data.message || 'Failed to load the message.'}</p>`;
                        console.error('Error loading message details:', data.message);
                    }
                })
                .catch(error => {
                    messageDetailsArea.classList.remove('loading');
                    messageDetailsArea.innerHTML = '<p class="error-message">A communication error occurred while loading the message.</p>';
                    console.error('Fetch error:', error);
                });
            }

            // Function to render message details HTML (create the HTML structure from JSON data)
            function renderMessageDetails(messageData) {
                // Determine tab name for return link
                 const urlParams = new URLSearchParams(window.location.search);
                 const currentTab = urlParams.get('tab') || 'inbox';
                 let returnTabName = '';
                 switch(currentTab) {
                      case 'inbox': returnTabName = 'Inbox'; break;
                      case 'sent': returnTabName = 'Sent'; break;
                      case 'archive': returnTabName = 'Archive'; break;
                      default: returnTabName = 'Messages';
                 }

                // Basic HTML structure - adapt this to match desired look
                let detailsHtml = `
                    <div class="message-view-container" data-message-id="${messageData.id}">
                        <div class="message-header">
                            <div class="message-nav">
                                <a href="messages.php?tab=${encodeURIComponent(currentTab)}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to ${returnTabName}
                                </a>

                                <div class="message-actions">
                                    ${messageData.receiver_id == currentUserId ? `
                                        <a href="send_message.php?reply_to=${messageData.id}" class="btn btn-primary">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                    ` : ''}

                                    <button class="btn btn-danger action-button" data-action="delete" data-message-id="${messageData.id}" data-confirm="Are you sure you want to delete this message?">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>

                                    ${messageData.receiver_id == currentUserId ? `
                                        ${currentTab !== 'archive' ? `
                                            <button class="btn btn-secondary action-button" data-action="archive" data-message-id="${messageData.id}">
                                                <i class="fas fa-archive"></i> Archive
                                            </button>
                                        ` : `
                                            <button class="btn btn-secondary action-button" data-action="unarchive" data-message-id="${messageData.id}">
                                                <i class="fas fa-inbox"></i> Restore
                                            </button>
                                        `}
                                    ` : ''}
                                </div>
                            </div>

                            <h2>${escapeHTML(messageData.subject)}</h2>
                        </div>

                        <div class="message-meta">
                            <div class="message-participants">
                                <div class="sender">
                                    <strong>From:</strong>
                                    <a href="player.php?id=${messageData.sender_id}" class="player-link">
                                        ${escapeHTML(messageData.sender_username)}
                                    </a>
                                </div>
                                <div class="receiver">
                                    <strong>To:</strong>
                                    <a href="player.php?id=${messageData.receiver_id}" class="player-link">
                                        ${escapeHTML(messageData.receiver_username)}
                                    </a>
                                </div>
                            </div>
                            <div class="message-date">
                                <strong>Date:</strong> ${formatDateTime(messageData.sent_at)}
                            </div>
                        </div>

                        <div class="message-content">
                            ${formatMessageBody(messageData.body)}
                        </div>
                    </div>
                `;

                messageDetailsArea.innerHTML = detailsHtml;
            }

            // Helper function to format date and time
            function formatDateTime(datetimeString) {
                const date = new Date(datetimeString);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0'); // Month is 0-indexed
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${day}.${month}.${year} ${hours}:${minutes}`;
            }

            // Helper function to format message body (e.g., replace newlines)
            function formatMessageBody(body) {
                return escapeHTML(body).replace(/\n/g, '<br>');
            }

            // Helper function to escape HTML characters to prevent XSS
            function escapeHTML(str) {
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            // --- Event listener for actions within the loaded message details ---
            // Using event delegation on the details area
            messageDetailsArea.addEventListener('click', function(event) {
                const actionButton = event.target.closest('.action-button');
                if (actionButton) {
                    const action = actionButton.dataset.action;
                    const messageId = actionButton.dataset.messageId;
                    const confirmMessage = actionButton.dataset.confirm;

                    if (confirmMessage && !confirm(confirmMessage)) {
                        return; // Cancel the action if the user did not confirm
                    }

                    // Perform the action via AJAX POST
                    performMessageAction(messageId, action);
                }
            });

            // Function to perform message actions via AJAX POST
            function performMessageAction(messageId, action) {
                // Show loading/disabling feedback (optional)

                const urlParams = new URLSearchParams(window.location.search);
                const currentTab = urlParams.get('tab') || 'inbox';

                fetch('view_message.php?id=' + messageId + '&tab=' + encodeURIComponent(currentTab), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                    },
                    body: new URLSearchParams({
                        action: action,
                        message_id: messageId,
                        // Add CSRF token if implemented
                        // csrf_token: 'your_token_here'
                    })
                })
                .then(response => {
                     if (!response.ok) {
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message); // Show success message
                        if (data.redirect) {
                            // Redirect if the action requires changing the view (e.g., delete, archive, unarchive)
                            window.location.href = data.redirect; // This will reload messages.php with correct tab
                        } else {
                            // If no redirect (e.g., mark as read/unread), update UI locally
                            // For mark_read/mark_unread, we need to update the message item class and status icon
                            if (action === 'mark_read' || action === 'mark_unread') {
                                const messageItem = messagesList.querySelector(`.message-item[data-message-id='${messageId}']`);
                                if (messageItem) {
                                     if (action === 'mark_read') {
                                         messageItem.classList.remove('unread');
                                     } else { // mark_unread
                                         messageItem.classList.add('unread');
                                     }
                                     const statusIcon = messageItem.querySelector('.status-icon');
                                     if(statusIcon) { // Update icon based on new read status
                                          if (messageItem.classList.contains('unread')) {
                                             statusIcon.classList.remove('fa-envelope-open', 'read-icon');
                                             statusIcon.classList.add('fa-envelope', 'unread-icon');
                                             statusIcon.title = 'Unread';
                                          } else {
                                             statusIcon.classList.remove('fa-envelope', 'unread-icon');
                                             statusIcon.classList.add('fa-envelope-open', 'read-icon');
                                             statusIcon.title = 'Read';
                                          }
                                     }
                                }
                            }
                            // Clear the details area or update it if needed
                            messageDetailsArea.innerHTML = '<p>Select a message from the list to view details.</p>'; // Clear details
                            // TODO: Update message counts in tabs after any action
                        }

                    } else {
                        alert('Error: ' + (data.message || 'Could not complete the action.'));
                    }
                })
                .catch(error => {
                    console.error('Action fetch error:', error);
                    alert('A communication error occurred while performing the action.');
                });
            }

            // --- Bulk Actions --- (Adapt the existing form submission)
            const messagesForm = document.getElementById('messages-form');
            const bulkActionSelect = document.getElementById('bulk-action');
            const bulkApplyButton = document.getElementById('bulk-apply');
            const messageCheckboxes = messagesList ? messagesList.querySelectorAll('.message-checkbox-input') : [];

            if (messagesForm && bulkActionSelect && bulkApplyButton && messageCheckboxes.length > 0) {

                 // Enable/disable apply button based on selection
                messagesList.addEventListener('change', function(event) {
                    if (event.target.classList.contains('message-checkbox-input')) {
                        const anyChecked = Array.from(messageCheckboxes).some(checkbox => checkbox.checked);
                        bulkApplyButton.disabled = !anyChecked;
                    }
                });

                messagesForm.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const selectedAction = bulkActionSelect.value;
                    const selectedMessageIds = Array.from(messageCheckboxes)
                        .filter(checkbox => checkbox.checked)
                        .map(checkbox => checkbox.value);

                    if (!selectedAction) {
                        alert('Please select an action.');
                        return;
                    }

                    if (selectedMessageIds.length === 0) {
                        alert('Please select messages.');
                        return;
                    }

                    // Optional: Add confirmation for delete action
                    if (selectedAction === 'delete' && !confirm('Are you sure you want to delete the selected messages?')) {
                        return;
                    }

                    // Perform bulk action via AJAX POST to messages.php
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentTab = urlParams.get('tab') || 'inbox';

                    // Add loading state or disable buttons
                    bulkApplyButton.disabled = true;

                    fetch(`messages.php?tab=${encodeURIComponent(currentTab)}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                        },
                        body: new URLSearchParams({
                            action: selectedAction,
                            message_ids: selectedMessageIds,
                            // Add CSRF token if implemented
                            // csrf_token: 'your_token_here'
                        })
                    })
                    .then(response => {
                         if (!response.ok) {
                              throw new Error(`HTTP error! status: ${response.status}`);
                         }
                         // Assuming messages.php POST will return JSON for AJAX requests
                         // Need to verify this in messages.php
                         return response.json();
                    })
                    .then(data => {
                         // Remove loading state or re-enable buttons
                         bulkApplyButton.disabled = false;

                        if (data.success) {
                            alert(data.message || 'Action completed successfully.');
                            // TODO: Update the UI based on the bulk action without full reload
                            // For now, reload the page to see changes
                             window.location.reload(); // Reloads with current tab and page
                        } else {
                            alert('Error: ' + (data.message || 'An error occurred while performing the bulk action.'));
                        }
                    })
                    .catch(error => {
                        bulkApplyButton.disabled = false;
                        console.error('Bulk action fetch error:', error);
                        alert('A communication error occurred while performing the bulk action.');
                    });
                });

            } else if (messagesForm && bulkActionSelect && bulkApplyButton) {
                 // Handle case where there are no messages to select
                 bulkApplyButton.disabled = true;
            }


            // Initial state: Check URL for a specific message ID and load it if present
            const urlParams = new URLSearchParams(window.location.search);
            const initialMessageId = urlParams.get('id');
            if (initialMessageId) {
                loadMessageDetails(initialMessageId);
            } else {
                 // If no message ID in URL, show placeholder text in details area
                 // Only set if the area is empty (i.e., not already populated by a non-AJAX call, which won't happen now)
                 if(messageDetailsArea.innerHTML.trim() === '<p>Select a message from the list to view details.</p>' || messageDetailsArea.innerHTML.trim() === '') {
                     messageDetailsArea.innerHTML = '<p>Select a message from the list to view details.</p>';
                 }
            }

        }
    });
</script>

<style>
/* Pagination styles (copied from messages.php if consistent) */
/*
.pagination {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-md);
    gap: var(--spacing-sm);
}

.pagination .page-link {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
    text-decoration: none;
    color: var(--brown-primary);
    background-color: var(--beige-light);
    transition: background-color var(--transition-fast), border-color var(--transition-fast);
}

.pagination .page-link:hover {
    background-color: var(--beige-dark);
    border-color: var(--brown-primary);
}

.pagination .page-link.active {
    background-color: var(--brown-primary);
    color: white;
    border-color: var(--brown-primary);
    cursor: default;
}
*/
</style> 
