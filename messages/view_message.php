<?php
require '../init.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../lib/managers/MessageManager.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';

// Access control - only for logged-in users
if (!isset($_SESSION['user_id'])) {
    // Redirect or return an error depending on the request type
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header("Location: ../auth/login.php");
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not logged in.', 'redirect' => 'auth/login.php']);
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Initialize VillageManager and fetch resources (needed for header.php)
$villageManager = new VillageManager($conn);
$village_id = $villageManager->getFirstVillage($user_id);
$village = $villageManager->getVillageInfo($village_id);

// Initialize the message manager
$messageManager = new MessageManager($conn);

// Ensure a message ID was provided
if (!isset($_GET['id'])) {
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header("Location: messages.php");
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Message ID is missing.']);
        exit();
    }
}
$msg_id = (int)$_GET['id'];

// Fetch the active tab if provided (used for back links)
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
$validTabs = ['inbox', 'sent', 'archive'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'inbox';
}

// --- Handle AJAX requests for message data ---
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retrieve the message via MessageManager (marks as read if needed)
    $msg = $messageManager->getMessageByIdForUser($msg_id, $user_id);

    if ($msg === null) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Message not found or access denied.']);
        exit();
    } else {
        // Return message data as JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'messageData' => $msg, // Return the full message data
            'message' => 'Message loaded successfully.'
        ]);
        exit(); // Stop script execution after returning JSON for AJAX GET
    }
}

// --- Handle message actions (POST - Delete, Archive, Unarchive) ---
// This section handles both AJAX POST and traditional POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_id_from_post = (int)($_POST['message_id'] ?? 0);

    // Ensure the message_id from POST matches the message being viewed ($msg_id)
    if ($message_id_from_post === $msg_id) {
        // Use MessageManager to perform the single action
        $success = $messageManager->performSingleAction($user_id, $msg_id, $action);

        if ($success) {
            // Define response based on action
            $response = ['success' => true, 'message' => 'Action completed successfully.'];
            // Prepare redirect URL after the action
            $redirectUrl = 'messages.php?tab=' . urlencode($activeTab);
            if ($action === 'delete') {
                 $response['message'] = 'Message deleted.';
                 // After deletion always redirect to the list
                 $response['redirect'] = $redirectUrl;
            } elseif ($action === 'archive') {
                 $response['message'] = 'Message moved to archive.';
                 // After archiving you can redirect to archive or refresh the list
                 $response['redirect'] = 'messages.php?tab=archive';
            } elseif ($action === 'unarchive') {
                 $response['message'] = 'Message restored from archive.';
                 // After restoring redirect to inbox
                 $response['redirect'] = 'messages.php?tab=inbox';
            }

            if ($is_ajax) {
                 header('Content-Type: application/json');
                 echo json_encode($response);
                 exit(); // Stop the script after responding to AJAX POST
            } else {
                // Traditional redirect after POST without AJAX
                 header("Location: " . $response['redirect']);
                 exit();
            }

        } else {
            $response = ['success' => false, 'message' => 'An error occurred while performing the action or you lack permission.'];
            if ($is_ajax) {
                 header('Content-Type: application/json');
                 echo json_encode($response);
                 exit();
            } else {
                 // Traditional redirect with error (could add an error parameter to the URL)
                 header("Location: messages.php?tab=" . urlencode($activeTab) . "&action_error=1");
                 exit();
            }
        }
    } else {
         $response = ['success' => false, 'message' => 'Invalid message identifier in the action request.'];
         if ($is_ajax) {
              header('Content-Type: application/json');
              echo json_encode($response);
              exit();
         } else {
              header("Location: messages.php?tab=" . urlencode($activeTab) . "&action_error=1");
              exit();
         }
    }
}

// --- Standard full-page rendering (non-AJAX) ---
// This path is used when a user visits view_message.php?id=X directly.
// It should fetch the message data and render the whole page.

// Fetch the message via MessageManager (marks as read if needed)
// Fetch again because previous retrieval may have been only for AJAX GET
$msg = $messageManager->getMessageByIdForUser($msg_id, $user_id);

if ($msg === null) {
    // Message not found or no access on direct entry
    header("Location: messages.php?tab=" . urlencode($activeTab) . "&error=message_not_found");
    exit();
}

// Determine whether the user is the sender or receiver
$is_sender = ($msg['sender_id'] == $user_id);
$is_receiver = ($msg['receiver_id'] == $user_id);

$pageTitle = 'Message: ' . htmlspecialchars($msg['subject']);
require '../header.php';
?>

<div id="game-container">
    <!-- Main page header injected by header.php -->

    <div id="main-content">
        <main>
            <div class="message-view-container" data-message-id="<?= $msg['id'] ?>" data-active-tab="<?= htmlspecialchars($activeTab) ?>">
                <div class="message-header">
                    <div class="message-nav">
                        <a href="messages.php?tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to <?= $activeTab === 'inbox' ? 'Inbox' : ($activeTab === 'sent' ? 'Sent' : 'Archive') ?>
                        </a>
                        
                        <div class="message-actions">
                            <?php if ($is_receiver): // Only the receiver can reply ?>
                                <a href="send_message.php?reply_to=<?= $msg_id ?>" class="btn btn-primary">
                                    <i class="fas fa-reply"></i> Reply
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-danger action-button" data-action="delete" data-message-id="<?= $msg_id ?>" data-confirm="Are you sure you want to delete this message?">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            
                            <?php if ($is_receiver): // Only the receiver can archive/restore ?>
                                <?php if ($activeTab !== 'archive'): ?>
                                    <button class="btn btn-secondary action-button" data-action="archive" data-message-id="<?= $msg_id ?>">
                                        <i class="fas fa-archive"></i> Archive
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary action-button" data-action="unarchive" data-message-id="<?= $msg_id ?>">
                                        <i class="fas fa-inbox"></i> Restore
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2><?= htmlspecialchars($msg['subject']) ?></h2>
                </div>
                
                <div class="message-meta">
                    <div class="message-participants">
                        <div class="sender">
                            <strong>From:</strong> 
                            <a href="../player/player.php?id=<?= $msg['sender_id'] ?>" class="player-link">
                                <?= htmlspecialchars($msg['sender_username']) ?>
                            </a>
                        </div>
                        <div class="receiver">
                            <strong>To:</strong> 
                            <a href="../player/player.php?id=<?= $msg['receiver_id'] ?>" class="player-link">
                                <?= htmlspecialchars($msg['receiver_username']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="message-date">
                        <strong>Date:</strong> <?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?>
                    </div>
                </div>
                
                <div class="message-content">
                    <?= nl2br(htmlspecialchars($msg['body'])) ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>

<script src="../js/messages.js"></script>
