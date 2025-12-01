<?php
declare(strict_types=1);
// require_once 'init.php'; // init.php is already included in header.php
?>
    <footer class="site-footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>Tribal Wars</h3>
            <p>Browser strategy game</p>
        </div>
        <div class="footer-links">
            <h4>Quick links</h4>
            <ul>
                <li><a href="auth/register.php">Register</a></li>
                <li><a href="auth/login.php">Log in</a></li>
                <li><a href="help.php">Help</a></li>
                <li><a href="terms.php">Terms</a></li>
            </ul>
        </div>
        <div class="footer-info">
            <h4>About the project</h4>
            <p>This version of Tribal Wars is a modern implementation of the classic strategy game. The project is built with PHP, MySQL, HTML5, and CSS3.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Tribal Wars. All rights reserved.</p>
    </div>
    </footer>

    <!-- Generic Modal -->
    <div id="generic-modal" class="popup-container" style="display:none;">
        <div class="popup-content large">
            <span class="close-button">&times;</span>
            <div id="generic-modal-content">
                <!-- Content will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
</body>
</html>
