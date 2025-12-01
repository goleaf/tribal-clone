<?php
declare(strict_types=1);
require_once '../../init.php';
require_once __DIR__ . '/../../lib/managers/GuideManager.php';

header('Content-Type: application/json');

$guideManager = new GuideManager($conn);
$guideManager->ensureSchema();
// Seed a couple of sample guides for empty installs
$guideManager->seedDefaults($_SESSION['user_id'] ?? null);

$isAdmin = !empty($_SESSION['is_admin']);

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$tags = array_filter(array_map('trim', explode(',', $_GET['tags'] ?? '')));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$locale = trim($_GET['locale'] ?? 'en');
$statusParam = $_GET['status'] ?? null;

$statuses = ['published'];
if ($isAdmin && $statusParam === 'all') {
    $statuses = ['draft', 'published', 'archived'];
}

$guides = $guideManager->searchGuides(
    $q,
    $category !== '' ? $category : null,
    $tags,
    $statuses,
    $limit,
    $offset,
    $locale
);

echo json_encode([
    'status' => 'success',
    'data' => [
        'items' => $guides,
        'total' => $guideManager->countGuides($statuses)
    ]
]);
