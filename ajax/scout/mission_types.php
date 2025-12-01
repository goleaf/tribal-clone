<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/IntelManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

$intel = new IntelManager($conn);
$types = $intel->getMissionTypes();

AjaxResponse::success(['mission_types' => $types]);
