<?php
session_start();
include 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in.']);
    exit();
}

// Only allow claiming if the server actually flagged this session as eligible —
// prevents someone from calling this endpoint repeatedly to farm XP.
if (empty($_SESSION['daily_reward_claimable'])) {
    echo json_encode(['ok' => false, 'text' => 'No reward available right now.']);
    exit();
}

$action = $_POST['action'] ?? 'claim';
unset($_SESSION['daily_reward_claimable']);

if ($action === 'decline') {
    echo json_encode(['ok' => true, 'declined' => true]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$bonus = 2;

$stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
$stmt->bind_param('ii', $bonus, $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'xp_gained' => $bonus]);
