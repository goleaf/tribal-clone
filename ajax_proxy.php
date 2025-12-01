<?php
// ajax_proxy.php
// Acts as a proxy for AJAX requests to get_resources.php.
// This works around the 404 issue that occurs when accessing get_resources.php directly.

// Include ajax/get_resources.php
require_once __DIR__ . '/ajax/resources/get_resources.php';

// After including get_resources.php, its code runs and AjaxResponse::success() or
// AjaxResponse::error() sends the JSON response and exits the script.
// No additional code is needed here.
?>
