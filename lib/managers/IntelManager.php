<?php
declare(strict_types=1);

/**
 * IntelManager
 *
 * Centralized helper for scouting/fog-of-war data:
 * - Records normalized intel reports (from spy missions or other sources)
 * - Computes freshness/quality/confidence metadata
 * - Provides tribe sharing and tagging helpers
 */
class IntelManager
{
    private $conn;
    private ?TribeManager $tribeManager = null;

    public function __construct($conn)
    {
        $this->conn = $conn;
        if (!class_exists('TribeManager')) {
            require_once __DIR__ . '/TribeManager.php';
        }
        $this->tribeManager = new TribeManager($conn);
    }

    /**
     * Fetch all configured mission types (seeded via migration).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMissionTypes(): array
    {
        $result = $this->conn->query("SELECT * FROM scout_mission_types ORDER BY id ASC");
        if ($result instanceof SQLiteResult || $result instanceof mysqli_result) {
            return $result->fetch_all();
        }
        return [];
    }

    /**
     * Fetch a mission type by internal name; returns null if missing.
     */
    public function getMissionType(string $internal): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM scout_mission_types WHERE internal_name = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("s", $internal);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $res && $res->free();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Persist a spy mission outcome as an intel report.
     *
     * Expected payload keys:
     * - attack_id (int|null)
     * - mission_type (string)
     * - source_village_id, target_village_id (int)
     * - source_user_id, target_user_id (int|null)
     * - details (array) => spy battle details incl. intel[]
     *
     * @return int|null Report ID
     */
    public function recordSpyReport(array $payload): ?int
    {
        $missionType = $payload['mission_type'] ?? 'light_scout';
        $details = $payload['details'] ?? [];

        $derived = $this->deriveMetricsFromDetails($missionType, $details);

        $sourceVillageId = (int)($payload['source_village_id'] ?? 0);
        $targetVillageId = (int)($payload['target_village_id'] ?? 0);
        $sourceUserId = (int)($payload['source_user_id'] ?? 0);
        $targetUserId = isset($payload['target_user_id']) ? (int)$payload['target_user_id'] : null;

        $villageMeta = $this->fetchVillageMeta([$sourceVillageId, $targetVillageId]);
        $sourceMeta = $villageMeta[$sourceVillageId] ?? [];
        $targetMeta = $villageMeta[$targetVillageId] ?? [];

        $intelJson = json_encode([
            'intel' => $details['intel'] ?? [],
            'raw' => $details,
            'scores' => $details['scores'] ?? null,
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO intel_reports (
                attack_id, mission_type, outcome, quality, confidence, detection, lost_units,
                source_village_id, source_village_name, source_user_id,
                target_village_id, target_village_name, target_x, target_y, target_user_id,
                intel_json, gathered_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            error_log('IntelManager::recordSpyReport prepare failed: ' . $this->conn->error);
            return null;
        }

        $attackId = $payload['attack_id'] ?? null;
        $gatheredAt = time();
        $createdAt = $gatheredAt;
        $sourceName = $sourceMeta['name'] ?? null;
        $targetName = $targetMeta['name'] ?? null;
        $targetX = $targetMeta['x_coord'] ?? null;
        $targetY = $targetMeta['y_coord'] ?? null;

        $stmt->bind_param(
            "issiiiiisiisiiisii",
            $attackId,
            $missionType,
            $derived['outcome'],
            $derived['quality'],
            $derived['confidence'],
            $derived['detection'],
            $derived['lost_units'],
            $sourceVillageId,
            $sourceName,
            $sourceUserId,
            $targetVillageId,
            $targetName,
            $targetX,
            $targetY,
            $targetUserId,
            $intelJson,
            $gatheredAt,
            $createdAt
        );
        $ok = $stmt->execute();
        $insertId = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $insertId ?: null;
    }

    /**
     * Get reports accessible to a user (owner or shared with their tribe).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getReportsForUser(int $userId, int $limit = 50, bool $includeShared = true): array
    {
        $limit = max(1, min(200, $limit));
        $tribeId = null;
        if ($includeShared && $this->tribeManager) {
            $membership = $this->tribeManager->getMembershipPublic($userId);
            $tribeId = $membership['tribe_id'] ?? null;
        }

        if ($tribeId) {
            $sql = "
                SELECT r.*, CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END AS shared_with_tribe
                FROM intel_reports r
                LEFT JOIN intel_shares s ON s.report_id = r.id AND s.tribe_id = ?
                WHERE r.source_user_id = ? OR r.target_user_id = ? OR s.id IS NOT NULL
                ORDER BY r.gathered_at DESC
                LIMIT ?
            ";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                return [];
            }
            $stmt->bind_param("iiii", $tribeId, $userId, $userId, $limit);
        } else {
            $sql = "
                SELECT r.*, 0 AS shared_with_tribe
                FROM intel_reports r
                WHERE r.source_user_id = ? OR r.target_user_id = ?
                ORDER BY r.gathered_at DESC
                LIMIT ?
            ";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                return [];
            }
            $stmt->bind_param("iii", $userId, $userId, $limit);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $res && $res->free();
        $stmt->close();

        $reportIds = array_map(static fn($r) => (int)$r['id'], $rows);
        $tagsByReport = $this->getTagsForReports($reportIds);

        foreach ($rows as &$row) {
            $fresh = $this->buildFreshnessMeta((int)$row['gathered_at']);
            $row['freshness'] = $fresh['status'];
            $row['freshness_label'] = $fresh['label'];
            $row['reliability'] = $fresh['reliability'];
            $row['age_seconds'] = $fresh['age_seconds'];
            $row['tags'] = $tagsByReport[$row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Share a report with the user's tribe.
     */
    public function shareReportWithTribe(int $reportId, int $userId): array
    {
        $report = $this->getReport($reportId);
        if (!$report) {
            return ['success' => false, 'message' => 'Report not found.'];
        }

        $membership = $this->tribeManager ? $this->tribeManager->getMembershipPublic($userId) : null;
        $tribeId = $membership['tribe_id'] ?? null;
        if (!$tribeId) {
            return ['success' => false, 'message' => 'Join a tribe to share intel.'];
        }

        // Ensure the user can access this report
        $canAccess = ((int)$report['source_user_id'] === $userId) ||
            ((int)($report['target_user_id'] ?? 0) === $userId) ||
            $this->isReportSharedWithTribe($reportId, $tribeId);

        if (!$canAccess) {
            return ['success' => false, 'message' => 'You cannot share a report you cannot access.'];
        }

        $stmt = $this->conn->prepare("
            INSERT OR IGNORE INTO intel_shares (report_id, tribe_id, shared_by_user_id)
            VALUES (?, ?, ?)
        ");
        if ($stmt === false) {
            return ['success' => false, 'message' => 'Unable to share report right now.'];
        }
        $stmt->bind_param("iii", $reportId, $tribeId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return [
            'success' => true,
            'message' => $affected > 0 ? 'Report shared with tribe.' : 'Report was already shared.',
            'tribe_id' => $tribeId
        ];
    }

    /**
     * Retrieve a single report with tags and freshness metadata.
     */
    public function getReport(int $reportId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM intel_reports WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $res && $res->free();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $row['tags'] = $this->getTagsForReports([$reportId])[$reportId] ?? [];
        $fresh = $this->buildFreshnessMeta((int)$row['gathered_at']);
        $row['freshness'] = $fresh['status'];
        $row['freshness_label'] = $fresh['label'];
        $row['reliability'] = $fresh['reliability'];
        $row['age_seconds'] = $fresh['age_seconds'];
        return $row;
    }

    /**
     * Compute freshness bucket and reliability score following the design doc.
     */
    public function buildFreshnessMeta(int $gatheredAt): array
    {
        $ageSeconds = max(0, time() - $gatheredAt);
        $hours = $ageSeconds / 3600;

        if ($hours <= 1) {
            return ['status' => 'fresh', 'label' => '0-1h', 'reliability' => 97, 'age_seconds' => $ageSeconds];
        }
        if ($hours <= 6) {
            return ['status' => 'recent', 'label' => '1-6h', 'reliability' => 90, 'age_seconds' => $ageSeconds];
        }
        if ($hours <= 24) {
            return ['status' => 'aging', 'label' => '6-24h', 'reliability' => 75, 'age_seconds' => $ageSeconds];
        }
        if ($hours <= 72) {
            return ['status' => 'stale', 'label' => '1-3d', 'reliability' => 55, 'age_seconds' => $ageSeconds];
        }
        if ($hours <= 168) {
            return ['status' => 'old', 'label' => '3-7d', 'reliability' => 35, 'age_seconds' => $ageSeconds];
        }
        return ['status' => 'ancient', 'label' => '7d+', 'reliability' => 10, 'age_seconds' => $ageSeconds];
    }

    /**
     * Returns age (seconds) of the newest intel report accessible to the user (own or tribe-shared).
     * Null if none exist or on error.
     */
    public function getLatestReportAgeSecondsForUser(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $tribeId = null;
            if ($this->tribeManager) {
                $membership = $this->tribeManager->getMembershipPublic($userId);
                $tribeId = $membership['tribe_id'] ?? null;
            }

            if ($tribeId) {
                $sql = "
                    SELECT MAX(r.gathered_at) AS newest
                    FROM intel_reports r
                    LEFT JOIN intel_shares s ON s.report_id = r.id AND s.tribe_id = ?
                    WHERE r.source_user_id = ? OR r.target_user_id = ? OR s.id IS NOT NULL
                ";
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    return null;
                }
                $stmt->bind_param("iii", $tribeId, $userId, $userId);
            } else {
                $sql = "
                    SELECT MAX(r.gathered_at) AS newest
                    FROM intel_reports r
                    WHERE r.source_user_id = ? OR r.target_user_id = ?
                ";
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    return null;
                }
                $stmt->bind_param("ii", $userId, $userId);
            }

            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $res && $res->free();
            $stmt->close();

            if (!$row || empty($row['newest'])) {
                return null;
            }
            $gatheredAt = (int)$row['newest'];
            if ($gatheredAt <= 0) {
                return null;
            }
            return max(0, time() - $gatheredAt);
        } catch (Throwable $e) {
            error_log('IntelManager::getLatestReportAgeSecondsForUser failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Attach tags for report IDs.
     *
     * @param array<int> $reportIds
     * @return array<int,array<int,array<string,string>>>
     */
    public function getTagsForReports(array $reportIds): array
    {
        if (empty($reportIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $types = str_repeat('i', count($reportIds));
        $stmt = $this->conn->prepare("
            SELECT irt.report_id, it.id AS tag_id, it.name, it.color
            FROM intel_report_tags irt
            JOIN intel_tags it ON it.id = irt.tag_id
            WHERE irt.report_id IN ($placeholders)
        ");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param($types, ...$reportIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $rid = (int)$row['report_id'];
            $map[$rid][] = [
                'id' => (int)$row['tag_id'],
                'name' => $row['name'],
                'color' => $row['color']
            ];
        }
        $res && $res->free();
        $stmt->close();
        return $map;
    }

    /**
     * Pull minimal metadata for villages.
     *
     * @param array<int> $villageIds
     * @return array<int,array<string,mixed>>
     */
    private function fetchVillageMeta(array $villageIds): array
    {
        $villageIds = array_values(array_filter(array_unique(array_map('intval', $villageIds))));
        if (empty($villageIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($villageIds), '?'));
        $types = str_repeat('i', count($villageIds));
        $stmt = $this->conn->prepare("SELECT id, name, x_coord, y_coord FROM villages WHERE id IN ($placeholders)");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param($types, ...$villageIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $meta = [];
        while ($row = $res->fetch_assoc()) {
            $meta[(int)$row['id']] = $row;
        }
        $res && $res->free();
        $stmt->close();
        return $meta;
    }

    private function deriveMetricsFromDetails(string $missionType, array $details): array
    {
        $success = !empty($details['success']);
        $spiesSent = (int)($details['attacker_spies_sent'] ?? 0);
        $spiesLost = (int)($details['attacker_spies_lost'] ?? 0);
        $spiesReturned = (int)($details['attacker_spies_returned'] ?? 0);
        $detection = $spiesLost > 0 || ((int)($details['defender_spies'] ?? 0) > 0);

        $intelLevel = (int)($details['attacker_spy_level'] ?? 0);
        if ($spiesReturned >= 5) {
            $intelLevel += 2;
        } elseif ($spiesReturned >= 2) {
            $intelLevel += 1;
        }

        $quality = $success
            ? min(100, max(50, 55 + ($intelLevel * 10) - ($spiesLost * 3)))
            : max(10, 35 - ($spiesLost * 2));

        // Confidence blends mission preset with freshness; keep it simple for now
        $mission = $this->getMissionType($missionType) ?: ['base_quality' => 60];
        $baseQuality = (int)($mission['base_quality'] ?? 60);
        $confidence = (int)round(($quality * 0.7) + ($baseQuality * 0.3));
        $confidence = max(5, min(100, $confidence));

        $outcome = $success ? ($spiesLost === 0 ? 'success' : 'partial') : ($spiesReturned > 0 ? 'partial' : 'failed');

        return [
            'outcome' => $outcome,
            'quality' => (int)$quality,
            'confidence' => $confidence,
            'detection' => $detection ? 1 : 0,
            'lost_units' => $spiesLost
        ];
    }

    private function isReportSharedWithTribe(int $reportId, int $tribeId): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM intel_shares WHERE report_id = ? AND tribe_id = ? LIMIT 1");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param("ii", $reportId, $tribeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        $res && $res->free();
        $stmt->close();
        return $exists;
    }
}
