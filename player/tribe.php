<?php
declare(strict_types=1);
require '../init.php';
require_once __DIR__ . '/../lib/managers/VillageManager.php';
require_once __DIR__ . '/../lib/managers/TribeManager.php';
require_once __DIR__ . '/../lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

$villageManager = new VillageManager($conn);
$tribeManager = new TribeManager($conn);

$village_id = $villageManager->getFirstVillage($user_id);
$village = $village_id ? $villageManager->getVillageInfo($village_id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $action = $_POST['action'] ?? '';
    $result = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'create':
            $name = $_POST['tribe_name'] ?? '';
            $tag = $_POST['tribe_tag'] ?? '';
            $description = $_POST['tribe_description'] ?? '';
            $result = $tribeManager->createTribe($user_id, $name, $tag, $description);
            break;
        case 'invite':
            $tribeId = (int)($_POST['tribe_id'] ?? 0);
            $targetPlayer = $_POST['target_player'] ?? '';
            $result = $tribeManager->inviteUser($tribeId, $user_id, $targetPlayer);
            break;
        case 'leave':
            $result = $tribeManager->leaveTribe($user_id);
            break;
        case 'disband':
            $tribeId = (int)($_POST['tribe_id'] ?? 0);
            $result = $tribeManager->disbandTribe($tribeId, $user_id);
            break;
        case 'invite_response':
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            $decision = $_POST['decision'] ?? 'decline';
            $result = $tribeManager->respondToInvitation($inviteId, $user_id, $decision);
            break;
    }

    setGameMessage($result['message'] ?? 'Action processed.', $result['success'] ? 'success' : 'error');
    header("Location: tribe.php");
    exit();
}

$currentTribe = $tribeManager->getTribeForUser($user_id);
$tribeMembers = $currentTribe ? $tribeManager->getTribeMembers((int)$currentTribe['id']) : [];
$tribeStats = $currentTribe ? $tribeManager->getTribeStats((int)$currentTribe['id']) : ['member_count' => 0, 'village_count' => 0, 'points' => 0];
$invitations = $tribeManager->getInvitationsForUser($user_id);

$pageTitle = 'Tribe';
require '../header.php';
?>

<div id="game-container">
    <header id="main-header">
        <div class="header-title">
            <span class="game-logo">&#9876;</span>
            <span>Tribe</span>
        </div>
        <div class="header-user">
            Player: <?= htmlspecialchars($username) ?><br>
            <?php if ($village): ?>
                <span class="village-name-display" data-village-id="<?= $village['id'] ?>"><?= htmlspecialchars($village['name']) ?> (<?= $village['x_coord'] ?>|<?= $village['y_coord'] ?>)</span>
            <?php endif; ?>
        </div>
    </header>

    <div id="main-content">
        <main>
            <?php if ($currentTribe): ?>
                <section class="tribe-hero" style="background:#fff7ec;border:1px solid #e0c9a6;border-radius:10px;padding:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
                    <div>
                        <div style="font-size:12px;color:#8d5c2c;letter-spacing:0.05em;text-transform:uppercase;">Your tribe</div>
                        <div style="font-size:24px;font-weight:700;color:#5a3b1a;">[<?= htmlspecialchars($currentTribe['tag'] ?? '') ?>] <?= htmlspecialchars($currentTribe['name']) ?></div>
                        <div style="color:#7a6347;margin-top:4px;">Role: <?= htmlspecialchars(ucfirst($currentTribe['role'])) ?></div>
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:10px 14px;min-width:120px;text-align:center;">
                            <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;">Points</div>
                            <div style="font-size:20px;font-weight:700;"><?= formatNumber($tribeStats['points']) ?></div>
                        </div>
                        <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:10px 14px;min-width:120px;text-align:center;">
                            <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;">Members</div>
                            <div style="font-size:20px;font-weight:700;"><?= $tribeStats['member_count'] ?></div>
                        </div>
                        <div class="stat-card" style="background:#fff;border:1px solid #e0c9a6;border-radius:8px;padding:10px 14px;min-width:120px;text-align:center;">
                            <div style="font-size:12px;text-transform:uppercase;color:#8d5c2c;">Villages</div>
                            <div style="font-size:20px;font-weight:700;"><?= $tribeStats['village_count'] ?></div>
                        </div>
                        <?php if ($currentTribe['role'] === 'leader'): ?>
                            <form method="POST" action="tribe.php" onsubmit="return confirm('Disband the tribe? This removes all members.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="disband">
                                <input type="hidden" name="tribe_id" value="<?= (int)$currentTribe['id'] ?>">
                                <button type="submit" class="btn btn-secondary" style="background:#b23b3b;border-color:#a53030;color:#fff;">Disband tribe</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="tribe.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="leave">
                                <button type="submit" class="btn btn-secondary" style="background:#c7852a;border-color:#b6741f;color:#fff;">Leave tribe</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="tribe-description" style="background:#fff;border:1px solid #e0c9a6;border-radius:10px;padding:16px;margin-bottom:16px;">
                    <h3 style="margin-top:0;margin-bottom:8px;">Tribe description</h3>
                    <p style="margin:0;color:#4a3c30;"><?= nl2br(htmlspecialchars($currentTribe['description'] ?? 'No description set.')) ?></p>
                </section>

                <section class="tribe-members" style="background:#fff;border:1px solid #e0c9a6;border-radius:10px;padding:16px;margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <h3 style="margin:0;">Members</h3>
                        <div style="color:#7a6347;"><?= count($tribeMembers) ?> member(s)</div>
                    </div>
                    <table class="ranking-table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tribeMembers as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['username']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($member['role'])) ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($member['joined_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>

                <?php if ($currentTribe['role'] === 'leader'): ?>
                    <section class="form-container" style="border:1px solid #e0c9a6;">
                        <h3>Invite player</h3>
                        <form method="POST" action="tribe.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="invite">
                            <input type="hidden" name="tribe_id" value="<?= (int)$currentTribe['id'] ?>">
                            <label for="target_player">Player name</label>
                            <input type="text" id="target_player" name="target_player" placeholder="Enter player name" required>
                            <button type="submit" class="btn btn-primary mt-2">Send invitation</button>
                        </form>
                    </section>
                <?php endif; ?>

            <?php else: ?>
                <section class="info-card" style="background:#fff7ec;border:1px solid #e0c9a6;border-radius:10px;padding:16px;margin-bottom:16px;">
                    <h3 style="margin-top:0;">You are not in a tribe yet</h3>
                    <p style="margin-bottom:0;">Create your own tribe or accept an invitation from another player.</p>
                </section>

                <section class="form-container" style="border:1px solid #e0c9a6;">
                    <h3>Create a tribe</h3>
                    <form method="POST" action="tribe.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="create">
                        <label for="tribe_name">Tribe name</label>
                        <input type="text" id="tribe_name" name="tribe_name" maxlength="64" required>

                        <label for="tribe_tag">Tag</label>
                        <input type="text" id="tribe_tag" name="tribe_tag" maxlength="12" required>

                        <label for="tribe_description">Description</label>
                        <textarea id="tribe_description" name="tribe_description" rows="4" placeholder="Tell others about your tribe"></textarea>

                        <button type="submit" class="btn btn-primary mt-2">Create tribe</button>
                    </form>
                </section>

                <section class="tribe-invitations" style="background:#fff;border:1px solid #e0c9a6;border-radius:10px;padding:16px;margin-top:16px;">
                    <h3 style="margin-top:0;">Invitations</h3>
                    <?php if (empty($invitations)): ?>
                        <div style="color:#7a6347;">No pending invitations.</div>
                    <?php else: ?>
                        <table class="ranking-table">
                            <thead>
                                <tr>
                                    <th>Tribe</th>
                                    <th>Tag</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invitations as $invite): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invite['tribe_name']) ?></td>
                                        <td>[<?= htmlspecialchars($invite['tribe_tag']) ?>]</td>
                                        <td style="display:flex;gap:8px;">
                                            <form method="POST" action="tribe.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="invite_response">
                                                <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                                <input type="hidden" name="decision" value="accept">
                                                <button type="submit" class="btn btn-primary">Accept</button>
                                            </form>
                                            <form method="POST" action="tribe.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="invite_response">
                                                <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                                <input type="hidden" name="decision" value="decline">
                                                <button type="submit" class="btn btn-secondary">Decline</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require '../footer.php'; ?>
