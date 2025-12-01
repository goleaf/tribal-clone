<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/IntelManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

$userId = (int)$_SESSION['user_id'];
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
$includeShared = !isset($_GET['include_shared']) || $_GET['include_shared'] !== '0';

$intel = new IntelManager($conn);
$reports = $intel->getReportsForUser($userId, $limit, $includeShared);

AjaxResponse::success(['reports' => $reports]);
