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
$unreadMessages = array_reduce($messages, function($carry, $msg) use ($activeTab) {
    if ($activeTab !== 'sent' && empty($msg['is_read'])) {
        return $carry + 1;
    }
    return $carry;
}, 0);

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

<div id="game-container" data-user-id="<?= htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8') ?>">
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
            <div class="messages-stats" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Total</div>
                    <div style="font-size:22px;font-weight:700;"><?= (int)$totalMessages ?></div>
                </div>
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Unread</div>
                    <div style="font-size:22px;font-weight:700;"><?= (int)$unreadMessages ?></div>
                </div>
                <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:12px 16px;min-width:160px;">
                    <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;letter-spacing:0.03em;">Page</div>
                    <div style="font-size:22px;font-weight:700;"><?= $currentPage ?> / <?= max(1, $totalPages) ?></div>
                </div>
            </div>
            
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
                        
                    <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        if ($startPage > 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="messages.php?tab=<?= $activeTab ?>&page=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor;
                        if ($endPage < $totalPages) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    ?>
                        
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

<script>
    window.currentUserId = <?= json_encode($user_id) ?>;
</script>
<script src="../js/messages.js"></script>
