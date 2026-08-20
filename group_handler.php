<?php
session_start();
include 'config/db.php';
include 'includes/avatars.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in first.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$groupId = (int) ($_POST['groupId'] ?? $_GET['groupId'] ?? 0);

if ($groupId <= 0 && $action !== 'find_or_create_by_subject') {
    echo json_encode(['ok' => false, 'text' => 'Unknown group.']);
    exit();
}

if ($action !== 'find_or_create_by_subject') {
    $groupCheck = $conn->query("SELECT id FROM community_groups WHERE id='$groupId'");
    if (!$groupCheck || $groupCheck->num_rows === 0) {
        echo json_encode(['ok' => false, 'text' => 'That group does not exist.']);
        exit();
    }
}

function sfIsMember($conn, $groupId, $user_id) {
    $r = $conn->query("SELECT 1 FROM community_group_members WHERE group_id='$groupId' AND user_id='$user_id'");
    return $r && $r->num_rows > 0;
}

// Basic contact-sharing guard: block phone numbers and common social-handle
// call-outs so members can't push people off-platform. Not bulletproof
// (no filter fully is) but catches the obvious cases.
function sfContainsContactInfo($text) {
    // Phone-like sequences: 7+ digits, optionally grouped with spaces/dashes/dots/parens.
    if (preg_match('/(\+?\d[\d\-\.\s\(\)]{6,}\d)/', $text)) {
        $digitsOnly = preg_replace('/\D/', '', $text);
        if (strlen($digitsOnly) >= 7) return true;
    }
    // Named platforms / handle patterns.
    $flagged = ['snapchat', 'snap chat', 'whatsapp', 'whats app', 'instagram', 'ig:', 'telegram',
                'tiktok', 'facebook', 'fb.com', 'twitter', ' x.com', 'discord', '@gmail', '@yahoo',
                '@outlook', 'phone number', 'my number', 'call me on', 'reach me on'];
    $lower = strtolower($text);
    foreach ($flagged as $f) {
        if (strpos($lower, $f) !== false) return true;
    }
    if (preg_match('/@[a-z0-9_.]{3,}/i', $text)) return true; // @handle style mentions
    return false;
}

if ($action === 'find_or_create_by_subject') {
    $subject = trim($_POST['subject'] ?? '');
    if ($subject === '') {
        echo json_encode(['ok' => false, 'text' => 'Type a subject first.']);
        exit();
    }
    $subjectEsc = $conn->real_escape_string($subject);
    $existing = $conn->query("SELECT id FROM community_groups WHERE subject = '$subjectEsc' OR name = '$subjectEsc' LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $gid = (int) $existing->fetch_assoc()['id'];
        $conn->query("INSERT IGNORE INTO community_group_members (group_id, user_id) VALUES ('$gid', '$user_id')");
        echo json_encode(['ok' => true, 'groupId' => $gid, 'created' => false]);
        exit();
    }
    $nameEsc = $conn->real_escape_string($subject);

    // Only creating a brand-new group requires Premium — joining an
    // existing one (handled above) is free for everyone.
    $premRow = $conn->query("SELECT is_premium FROM users WHERE id='$user_id'")->fetch_assoc();
    if (empty($premRow['is_premium'])) {
        echo json_encode(['ok' => false, 'text' => 'Creating a new group is a Premium feature.', 'requiresPremium' => true]);
        exit();
    }

    $descEsc = $conn->real_escape_string("Study group for $subject — ask, answer, share strategy.");
    $conn->query("INSERT INTO community_groups (name, subject, description, icon) VALUES ('$nameEsc', '$subjectEsc', '$descEsc', '💬')");
    $gid = $conn->insert_id;
    $conn->query("INSERT IGNORE INTO community_group_members (group_id, user_id) VALUES ('$gid', '$user_id')");
    echo json_encode(['ok' => true, 'groupId' => $gid, 'created' => true]);
    exit();
}

if ($action === 'join') {
    $conn->query("INSERT IGNORE INTO community_group_members (group_id, user_id) VALUES ('$groupId', '$user_id')");
    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'leave') {
    $conn->query("DELETE FROM community_group_members WHERE group_id='$groupId' AND user_id='$user_id'");
    echo json_encode(['ok' => true]);
    exit();
}

if (!sfIsMember($conn, $groupId, $user_id)) {
    echo json_encode(['ok' => false, 'text' => 'Join this group first.']);
    exit();
}

if ($action === 'post') {
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        echo json_encode(['ok' => false, 'text' => 'Message is empty.']);
        exit();
    }
    if (sfContainsContactInfo($body)) {
        echo json_encode(['ok' => false, 'text' => "For everyone's safety, StudyFlow group chats don't allow sharing phone numbers, Snapchat, or other social handles. Keep the conversation on-platform."]);
        exit();
    }
    $bodyEsc = $conn->real_escape_string($body);
    $conn->query("INSERT INTO community_messages (group_id, user_id, body) VALUES ('$groupId', '$user_id', '$bodyEsc')");
    $newId = $conn->insert_id;
    $nameRes = $conn->query("SELECT name, is_premium, avatar FROM users WHERE id='$user_id'");
    $nameRow = $nameRes && $nameRes->num_rows > 0 ? $nameRes->fetch_assoc() : ['name' => 'You', 'is_premium' => 0, 'avatar' => null];
    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => $newId,
            'user_id' => $user_id,
            'name' => $nameRow['name'],
            'premium' => (bool) $nameRow['is_premium'],
            'avatar' => sfAvatarEmoji($nameRow['avatar']) ?? '🙂',
            'body' => $body,
            'created_at' => date('c'),
        ],
    ]);
    exit();
}

if ($action === 'fetch') {
    $afterId = (int) ($_GET['afterId'] ?? $_POST['afterId'] ?? 0);
    $rows = [];
    $stmt = $conn->prepare("SELECT m.id, m.user_id, m.body, m.attachment_path, m.attachment_type, m.attachment_name, m.created_at, u.name, u.is_premium, u.avatar
                          FROM community_messages m JOIN users u ON u.id = m.user_id
                          WHERE m.group_id = ? AND m.id > ?
                          ORDER BY m.id ASC LIMIT 100");
    $stmt->bind_param('ii', $groupId, $afterId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int) $r['id'],
                'user_id' => (int) $r['user_id'],
                'name' => $r['name'],
                'premium' => (bool) $r['is_premium'],
                'avatar' => sfAvatarEmoji($r['avatar']) ?? '🙂',
                'body' => $r['body'],
                'attachment_path' => $r['attachment_path'],
                'attachment_type' => $r['attachment_type'],
                'attachment_name' => $r['attachment_name'],
                'created_at' => $r['created_at'],
            ];
        }
    }
    $stmt->close();
    echo json_encode(['ok' => true, 'messages' => $rows]);
    exit();
}

echo json_encode(['ok' => false, 'text' => 'Unknown action.']);
