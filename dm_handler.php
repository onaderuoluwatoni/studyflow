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
$action = $_REQUEST['action'] ?? '';
$withId = (int) ($_REQUEST['with'] ?? 0);

if ($withId <= 0) {
    echo json_encode(['ok' => false, 'text' => 'Unknown conversation.']);
    exit();
}

$rel = sfFriendStatus($conn, $user_id, $withId);
if (!$rel || $rel['status'] !== 'accepted') {
    echo json_encode(['ok' => false, 'text' => 'You can only message accepted friends.']);
    exit();
}

/**
 * Deletes any message between these two people whose time is up.
 * Called on every post/fetch so we don't need a cron job.
 */
function sfCleanupExpiredDms($conn, $a, $b) {
    $stmt = $conn->prepare("
        SELECT id, attachment_path FROM direct_messages
        WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
        AND expires_at IS NOT NULL AND expires_at <= NOW()
    ");
    $stmt->bind_param('iiii', $a, $b, $b, $a);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($r = $res->fetch_assoc()) {
        $ids[] = (int) $r['id'];
        if ($r['attachment_path'] && file_exists(__DIR__ . '/' . $r['attachment_path'])) {
            @unlink(__DIR__ . '/' . $r['attachment_path']);
        }
    }
    $stmt->close();
    if ($ids) {
        $conn->query("DELETE FROM direct_messages WHERE id IN (" . implode(',', $ids) . ")");
    }
}

/** Computes expires_at for a given disappearing-message mode, or null. */
function sfExpiresAtFor($mode) {
    switch ($mode) {
        case '6h':  return date('Y-m-d H:i:s', time() + 6 * 3600);
        case '12h': return date('Y-m-d H:i:s', time() + 12 * 3600);
        case '24h': return date('Y-m-d H:i:s', time() + 24 * 3600);
        case '7d':  return date('Y-m-d H:i:s', time() + 7 * 86400);
        default:    return null; // 'after_view' and 'never' both start with no expiry
    }
}

/**
 * Saves an uploaded DM file (attachment or voice note) safely: validates
 * mime type and size, writes it with a random filename so nothing can be
 * guessed or overwritten, and returns a web-relative path to store in the DB.
 */
function sfSaveDmUpload($file, $allowedTypes, $maxBytes) {
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'text' => 'File is too large.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes, true)) {
        return ['ok' => false, 'text' => 'That file type is not supported.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = $ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';
    $filename = bin2hex(random_bytes(16)) . $ext;
    $destRelative = 'uploads/dm/' . $filename;
    $destAbsolute = __DIR__ . '/' . $destRelative;

    if (!move_uploaded_file($file['tmp_name'], $destAbsolute)) {
        return ['ok' => false, 'text' => 'Could not save the file — please try again.'];
    }

    return ['ok' => true, 'path' => $destRelative, 'type' => $mime];
}

sfCleanupExpiredDms($conn, $user_id, $withId);

if ($action === 'post') {
    $body = trim($_POST['body'] ?? '');
    $disappearMode = trim($_POST['disappear_mode'] ?? 'never');
    if (!in_array($disappearMode, ['after_view', '6h', '12h', '24h', '7d', 'never'], true)) {
        $disappearMode = 'never';
    }

    $attachmentPath = null;
    $attachmentType = null;
    $attachmentName = null;

    // Voice note (recorded in-browser, sent as a blob) or a regular file upload —
    // whichever is present takes priority; a message needs text or an attachment.
    if (isset($_FILES['voice']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
        $up = sfSaveDmUpload($_FILES['voice'], ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav'], 8 * 1024 * 1024);
        if ($up['ok']) {
            $attachmentPath = $up['path'];
            $attachmentType = $up['type'];
            $attachmentName = 'Voice note';
        } else {
            echo json_encode(['ok' => false, 'text' => $up['text']]);
            exit();
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
        $up = sfSaveDmUpload($_FILES['file'], $allowed, 15 * 1024 * 1024);
        if ($up['ok']) {
            $attachmentPath = $up['path'];
            $attachmentType = $up['type'];
            $attachmentName = $_FILES['file']['name'];
        } else {
            echo json_encode(['ok' => false, 'text' => $up['text']]);
            exit();
        }
    }

    if ($body === '' && !$attachmentPath) {
        echo json_encode(['ok' => false, 'text' => 'Message is empty.']);
        exit();
    }

    $expiresAt = sfExpiresAtFor($disappearMode);

    $stmt = $conn->prepare("INSERT INTO direct_messages (sender_id, recipient_id, body, attachment_path, attachment_type, attachment_name, disappear_mode, expires_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssss', $user_id, $withId, $body, $attachmentPath, $attachmentType, $attachmentName, $disappearMode, $expiresAt);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => $newId, 'sender_id' => $user_id, 'body' => $body,
            'attachment_path' => $attachmentPath, 'attachment_type' => $attachmentType, 'attachment_name' => $attachmentName,
            'created_at' => date('c'),
        ],
    ]);
    exit();
}

if ($action === 'fetch') {
    $afterId = (int) ($_GET['afterId'] ?? 0);
    $stmt = $conn->prepare("
        SELECT id, sender_id, body, attachment_path, attachment_type, attachment_name, disappear_mode, read_at, created_at
        FROM direct_messages
        WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)) AND id > ?
        ORDER BY id ASC LIMIT 100
    ");
    $stmt->bind_param('iiiii', $user_id, $withId, $withId, $user_id, $afterId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    $toMarkAfterView = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $r['id'], 'sender_id' => (int) $r['sender_id'], 'body' => $r['body'],
            'attachment_path' => $r['attachment_path'], 'attachment_type' => $r['attachment_type'], 'attachment_name' => $r['attachment_name'],
            'created_at' => $r['created_at'],
        ];
        // A message sent TO me, seen for the first time, with "after viewing" mode —
        // give it a short grace window then let the next cleanup pass remove it.
        if ((int) $r['sender_id'] !== $user_id && !$r['read_at'] && $r['disappear_mode'] === 'after_view') {
            $toMarkAfterView[] = (int) $r['id'];
        }
    }
    $stmt->close();

    // Mark everything sent to me as read (view receipt), regardless of disappearing mode.
    $conn->query("UPDATE direct_messages SET read_at = NOW() WHERE sender_id = '$withId' AND recipient_id = '$user_id' AND read_at IS NULL");

    if ($toMarkAfterView) {
        $graceExpiry = date('Y-m-d H:i:s', time() + 15); // 15s to actually see it before it's gone
        $idList = implode(',', $toMarkAfterView);
        $conn->query("UPDATE direct_messages SET expires_at = '$graceExpiry' WHERE id IN ($idList)");
    }

    echo json_encode(['ok' => true, 'messages' => $rows]);
    exit();
}

echo json_encode(['ok' => false, 'text' => 'Unknown action.']);
