<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/utils/AjaxResponse.php';
require_once __DIR__ . '/../../lib/managers/IntelManager.php';

if (!isset($_SESSION['user_id'])) {
    AjaxResponse::error('User is not logged in', null, 401);
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$reportId = isset($payload['report_id']) ? (int)$payload['report_id'] : 0;

if ($reportId <= 0) {
    AjaxResponse::error('Report id is required', null, 400);
}

$intel = new IntelManager($conn);
$result = $intel->shareReportWithTribe($reportId, (int)$_SESSION['user_id']);

if ($result['success']) {
    AjaxResponse::success($result);
}

AjaxResponse::error($result['message'] ?? 'Unable to share report right now.');
