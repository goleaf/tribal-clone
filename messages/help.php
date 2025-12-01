<?php
require '../init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Messages Help';
require '../header.php';
?>

<div id="game-container">
    <div id="main-content">
        <main>
            <h2>Messaging Help</h2>
            <p>Use this page as a quick reference for the in-game messaging system.</p>
            <ul>
                <li>Inbox: view and manage messages other players sent you.</li>
                <li>Sent: review messages you have sent.</li>
                <li>Archive: move messages here to keep your inbox clean; restore anytime.</li>
                <li>Select multiple messages and choose a bulk action (read, unread, archive, delete).</li>
                <li>Reply to a message directly from its preview or detail view.</li>
                <li>To write a new message, click “Write a message” on the Messages page or go to <a href="send_message.php">send_message.php</a>.</li>
            </ul>
            <p>If you need general game help, visit the <a href="../help.php">Game Help</a> page.</p>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>
