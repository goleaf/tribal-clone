<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/managers/GuideManager.php';

class GuideTestRunner
{
    private array $results = [];

    public function add(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->results[] = ['name' => $name, 'status' => 'passed'];
        } catch (Throwable $e) {
            $this->results[] = ['name' => $name, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    public function run(): void
    {
        $fails = 0;
        foreach ($this->results as $result) {
            if ($result['status'] === 'passed') {
                echo "[PASS] {$result['name']}\n";
            } else {
                $fails++;
                echo "[FAIL] {$result['name']}: {$result['message']}\n";
            }
        }
        echo "----\n" . (count($this->results) - $fails) . " passed, {$fails} failed\n";
        if ($fails > 0) exit(1);
    }
}

function assertTrue(bool $cond, string $message): void
{
    if (!$cond) throw new RuntimeException($message);
}

function assertEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " (expected " . var_export($expected, true) . " got " . var_export($actual, true) . ")");
    }
}

$runner = new GuideTestRunner();

$runner->add('Create + fetch published guide', function () {
    $conn = new SQLiteAdapter(':memory:');
    $manager = new GuideManager($conn);
    $manager->ensureSchema();

    $result = $manager->createGuide('Test Guide', 'Summary', '<p>Body</p>', 'test,guide', 'basics', 'published', 1);
    assertTrue($result['success'], 'Guide create should succeed');

    $guide = $manager->getGuideBySlug($result['slug']);
    assertTrue($guide !== null, 'Published guide should be visible');
    assertEquals('published', $guide['status'], 'Status should be published');
});

$runner->add('Draft guides hidden for non-admin', function () {
    $conn = new SQLiteAdapter(':memory:');
    $manager = new GuideManager($conn);
    $manager->ensureSchema();

    $manager->createGuide('Draft Guide', 'Summary', '<p>Body</p>', 'draft', 'basics', 'draft', 1);
    $guide = $manager->getGuideBySlug('draft-guide');
    assertTrue($guide === null, 'Draft should be hidden by default');

    $adminGuide = $manager->getGuideBySlug('draft-guide', true);
    assertTrue($adminGuide !== null, 'Draft should be visible when includeDraft=true');
});

$runner->add('Search filters by tag and category', function () {
    $conn = new SQLiteAdapter(':memory:');
    $manager = new GuideManager($conn);
    $manager->ensureSchema();

    $manager->createGuide('Combat 101', 'Summary', '<p>Combat</p>', 'combat,wall', 'combat', 'published', 1);
    $manager->createGuide('Economy 101', 'Summary', '<p>Eco</p>', 'economy', 'economy', 'published', 1);

    $filtered = $manager->searchGuides('', 'combat', ['wall'], ['published'], 10, 0);
    assertEquals(1, count($filtered), 'Should return one combat guide');
    assertEquals('Combat 101', $filtered[0]['title'], 'Should return combat guide');
});

$runner->run();
