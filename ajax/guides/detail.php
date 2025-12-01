<?php
declare(strict_types=1);
require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/GuideManager.php';

header('Content-Type: application/json');

$guideManager = new GuideManager($conn);
$guideManager->ensureSchema();
$isAdmin = !empty($_SESSION['is_admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

if (!$id && !$slug) {
    echo json_encode(['status' => 'error', 'message' => 'Missing id or slug.']);
    exit;
}

$guide = $slug ? $guideManager->getGuideBySlug($slug, $isAdmin) : $guideManager->getGuideById($id, $isAdmin);

if (!$guide) {
    echo json_encode(['status' => 'error', 'message' => 'Guide not found or not published.']);
    exit;
}

echo json_encode(['status' => 'success', 'data' => $guide]);
