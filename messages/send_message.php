<?php
require '../init.php';
validateCSRF();

require_once __DIR__ . '/../lib/managers/UserManager.php'; // For getting recipient user ID
require_once __DIR__ . '/../lib/managers/MessageManager.php'; // For message operations
require_once __DIR__ . '/../lib/managers/NotificationManager.php'; // For notifications
require_once __DIR__ . '/../lib/functions.php'; // For addNotification (if still needed, or move to manager)

// Access guard - only for logged-in users
if (!isset($_SESSION['user_id'])) {
    // For AJAX requests return JSON, otherwise redirect
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not logged in.', 'redirect' => 'auth/login.php']);
        exit();
    } else {
    header("Location: ../auth/login.php");
    exit();
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Managers
$userManager = new UserManager($conn);
$messageManager = new MessageManager($conn);
$notificationManager = new NotificationManager($conn); // Assuming constructor takes $conn

// Reply handling (pre-fill form)
$reply_to = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : 0;
$recipient_username = '';
$original_subject = '';
$original_body = '';
$prefilled_subject = '';

if ($reply_to > 0) {
    // Fetch original message (only if the user is the receiver)
    $original_message = $messageManager->getMessageByIdForUser($reply_to, $user_id); // Assuming this method checks receiver_id

    if ($original_message) {
        $recipient_username = $userManager->getUserById($original_message['sender_id'])['username'] ?? ''; // Get sender's username
        $original_subject = $original_message['subject'];
        $original_body = $original_message['body'];
        
        // Add "Re:" prefix if missing
        if (strpos($original_subject, 'Re:') !== 0) {
            $prefilled_subject = 'Re: ' . $original_subject;
        } else {
            $prefilled_subject = $original_subject;
        }
    }
}

// Handle send message (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    validateCSRF(); // Validate CSRF token
    
    $receiver_username = trim($_POST['receiver_username'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    $response = ['success' => false, 'message' => ''];

    if (empty($receiver_username) || empty($subject) || empty($body)) {
        $response['message'] = 'All fields are required!';
    } else {
        // Find recipient by username
        $receiver = $userManager->getUserByUsername($receiver_username);

        if ($receiver) {
            $receiver_id = $receiver['id'];

            // Prevent sending to self
            if ($receiver_id == $user_id) {
                $response['message'] = 'You cannot send a message to yourself.';
            } else {
                // Send the message via MessageManager
                $sendMessageResult = $messageManager->sendMessage($user_id, $receiver_id, $subject, $body);

                if ($sendMessageResult['success']) {
                    $response['success'] = true;
                    $response['message'] = 'Message sent successfully!';
                    $response['newMessageId'] = $sendMessageResult['message_id'];
                    $response['redirect'] = 'messages.php?tab=sent'; // Temporary redirect

                     // Add notification for the recipient
                     $notification_message = "You received a new message from {$username}";
                     $notification_link = "view_message.php?id=" . $sendMessageResult['message_id'];
                     $notificationManager->addNotification($receiver_id, $notification_message, 'info', $notification_link);

                } else {
                    $response['message'] = 'Error while sending the message.';
                }
            }
        } else {
            $response['message'] = 'User not found: ' . htmlspecialchars($receiver_username);
        }
    }
    
    echo json_encode($response);
    exit(); // End script after AJAX response
}

// Prepare data and render HTML form (for direct access or AJAX injection)
$formHtml = '';
ob_start(); // Start output buffering
?>

<div class="message-compose-container" data-reply-to="<?= $reply_to ?>">
    <h2>Write a new message</h2>
    
    <?php /* if (!empty($message)): ?>
        <div class="message-container">
            <?= $message ?>
        </div>
    <?php endif; */ ?>
    
    <form action="send_message.php<?= $reply_to ? '?reply_to=' . $reply_to : '' ?>" method="POST" class="message-compose-form" id="send-message-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <div class="form-group">
            <label for="receiver_username">Recipient:</label>
            <input type="text" id="receiver_username" name="receiver_username" value="<?= htmlspecialchars($recipient_username) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($prefilled_subject) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="body">Message body:</label>
            <textarea id="body" name="body" rows="10" required><?php 
            if ($reply_to > 0 && !empty($original_body)) {
                echo "\n\n---\nOn " . date('d.m.Y', strtotime($original_message['sent_at'] ?? 'now')) . ", " . htmlspecialchars($recipient_username) . " wrote:\n";
                $quoted_body = '';
                $lines = explode("\n", $original_body);
                foreach ($lines as $line) {
                    $quoted_body .= "> " . $line . "\n";
                }
                echo htmlspecialchars($quoted_body);
            }
            ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send message
            </button>
            <a href="messages.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    
    <?php if ($reply_to > 0 && !empty($original_body)): ?>
    <div class="original-message">
        <h3>Original message</h3>
        <div class="original-message-content">
            <div class="original-message-header">
                <div><strong>From:</strong> <?= htmlspecialchars($recipient_username) ?></div>
                <div><strong>Subject:</strong> <?= htmlspecialchars($original_subject) ?></div>
            </div>
            <div class="original-message-body">
                <?= nl2br(htmlspecialchars($original_body)) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$formHtml = ob_get_clean(); // Capture buffer and stop buffering

// Detect AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    // Return only the form HTML (for popup use) or JSON after POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
         // If it's a GET request (e.g., to get the form HTML for a popup)
         echo json_encode([
             'status' => 'success',
             'html' => $formHtml
         ]);
         exit();
    }
    // POST requests are handled and exit earlier
} else {
    // If it's a standard page request (not AJAX)
    $pageTitle = 'Write a message';
    require 'header.php';
    ?>
    <div id="game-container">
        <?php // Add header ?>
         <header id="main-header">
             <div class="header-title">
                 <span class="game-logo">&#128231;</span>
                 <span>New message</span>
             </div>
             <?php // User section will be included by header.php if logic is there ?>
             <?php if (isset($_SESSION['user_id']) && ($currentUserVillage = $villageManager->getFirstVillage($_SESSION['user_id']))): ?>
              <div class="header-user">
                  Player: <?= htmlspecialchars($_SESSION['username']) ?><br>
                  <span class="village-name-display" data-village-id="<?= $currentUserVillage['id'] ?>"><?= htmlspecialchars($currentUserVillage['name']) ?> (<?= $currentUserVillage['x_coord'] ?>|<?= $currentUserVillage['y_coord'] ?>)</span>
              </div>
             <?php endif; ?>
         </header>
        <div id="main-content">
            <main>
                <?= $formHtml // Output the buffered form HTML ?>
            </main>
        </div>
    </div>
    <?php
    require 'footer.php';
}

?>

<script>
// Script to handle sending messages via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('send-message-form');
    if (!form) return;

    form.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default submit

        // Show loader
        showLoading(); // Requires global showLoading()

        const formData = new FormData(form);

        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('success', data.message);
                 if (data.redirect) {
                     window.location.href = data.redirect; // Temporary redirect on success
                 } else {
                     // When no redirect, e.g., popup: form.reset(); close popup if needed
                 }

            } else {
                showToast('error', data.message);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('AJAX error:', error);
            showToast('error', 'A communication error occurred.');
        });
    });
});

// Expected global helpers (define in main.js or another shared file)
// function showLoading() { /* implementation */ }
// function hideLoading() { /* implementation */ }
// function showToast(type, message) { /* implementation */ }
</script>

<style>
/* Styles specific to the send message form */

/* Removed global game container/sidebar styles because this file can be loaded via AJAX */

.message-compose-container {
    background-color: var(--beige-light);
    border-radius: var(--border-radius-medium);
    box-shadow: var(--box-shadow-default);
    padding: var(--spacing-lg);
    margin-top: var(--spacing-md);
}

.message-compose-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.form-group label {
    font-weight: bold;
    color: var(--brown-secondary);
}

.form-group input[type="text"],
.form-group textarea {
    padding: var(--spacing-sm);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
    background-color: #fff;
    font-family: var(--font-main);
    font-size: var(--font-size-normal);
    width: calc(100% - var(--spacing-sm) * 2); /* Account for padding */
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 200px;
}

.form-actions {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: flex-end;
    margin-top: var(--spacing-md);
}

/* Styles for quoted original message */
.original-message {
    margin-top: var(--spacing-lg);
    padding: var(--spacing-md);
    background-color: var(--beige-dark);
    border: 1px solid var(--beige-darker);
    border-radius: var(--border-radius-small);
}

.original-message h3 {
    margin-top: 0;
    margin-bottom: var(--spacing-sm);
    color: var(--brown-primary);
    border-bottom: 1px solid var(--beige-darker);
    padding-bottom: var(--spacing-xs);
}

.original-message-content {
    font-size: var(--font-size-small);
    color: var(--brown-secondary);
}

.original-message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
}

.original-message-body {
    white-space: pre-wrap;
    word-break: break-word;
}

</style> 
