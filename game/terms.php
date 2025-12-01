<?php
// Convenience redirect if someone hits /game/terms.php; canonical terms live at /terms.php
header('Location: /terms.php', true, 302);
exit();
