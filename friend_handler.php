<?php
session_start();
include 'config/db.php';
include 'includes/friends.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'search') {
    $q = trim($_POST['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['ok' => true, 'results' => []]);
        exit();
    }
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE username LIKE ? AND id != ? LIMIT 10");
    $stmt->bind_param('si', $like, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $results = [];
    while ($r = $res->fetch_assoc()) {
        $rel = sfFriendStatus($conn, $user_id, (int) $r['id']);
        $results[] = [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'username' => $r['username'],
            'status' => $rel ? $rel['status'] : 'none',
        ];
    }
    $stmt->close();
    echo json_encode(['ok' => true, 'results' => $results]);
    exit();
}

if ($action === 'request') {
    $targetId = (int) ($_POST['target_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $user_id) {
        echo json_encode(['ok' => false, 'text' => 'Invalid user.']);
        exit();
    }
    $existing = sfFriendStatus($conn, $user_id, $targetId);
    if ($existing) {
        echo json_encode(['ok' => false, 'text' => 'A relationship already exists with this user.']);
        exit();
    }
    $stmt = $conn->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param('ii', $user_id, $targetId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'text' => 'Friend request sent.']);
    exit();
}

if ($action === 'accept' || $action === 'decline') {
    $friendshipId = (int) ($_POST['friendship_id'] ?? 0);
    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND addressee_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $friendshipId, $user_id);
        $stmt->execute();
        $stmt->close();

        if ($conn->affected_rows > 0) {
            // +2 XP for both people — small reward for growing your circle.
            $fr = $conn->prepare("SELECT requester_id, addressee_id FROM friendships WHERE id = ?");
            $fr->bind_param('i', $friendshipId);
            $fr->execute();
            $pair = $fr->get_result()->fetch_assoc();
            $fr->close();

            if ($pair) {
                $xpStmt = $conn->prepare("UPDATE users SET xp = xp + 2 WHERE id = ? OR id = ?");
                $xpStmt->bind_param('ii', $pair['requester_id'], $pair['addressee_id']);
                $xpStmt->execute();
                $xpStmt->close();
            }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM friendships WHERE id = ? AND addressee_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $friendshipId, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'remove') {
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM friendships WHERE status = 'accepted' AND
                             ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))");
    $stmt->bind_param('iiii', $user_id, $targetId, $targetId, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'block') {
    $targetId = (int) ($_POST['target_id'] ?? 0);
    // Clear any existing relationship first, then insert a fresh block from this user.
    $stmt = $conn->prepare("DELETE FROM friendships WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)");
    $stmt->bind_param('iiii', $user_id, $targetId, $targetId, $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'blocked')");
    $stmt->bind_param('ii', $user_id, $targetId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'unblock') {
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = 'blocked'");
    $stmt->bind_param('ii', $user_id, $targetId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit();
}

echo json_encode(['ok' => false, 'text' => 'Unknown action.']);
