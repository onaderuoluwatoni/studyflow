<?php
session_start();
include 'config/db.php';
include 'group_handler_shared.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$groupId = (int) ($_POST['group_id'] ?? 0);

if ($groupId <= 0 || !sfIsMember($conn, $groupId, $user_id)) {
    echo json_encode(['ok' => false, 'text' => 'You are not a member of that group.']);
    exit();
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'text' => 'Upload failed — please try again.']);
    exit();
}

$file = $_FILES['attachment'];
$maxBytes = 8 * 1024 * 1024; // 8MB — conservative for free-tier hosting
if ($file['size'] > $maxBytes) {
    echo json_encode(['ok' => false, 'text' => 'File is too large — 8MB max.']);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$docExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
$audioExts = ['webm', 'ogg', 'mp3', 'm4a', 'wav'];

if (in_array($ext, $imageExts, true)) $type = 'image';
elseif (in_array($ext, $audioExts, true)) $type = 'audio';
elseif (in_array($ext, $docExts, true)) $type = 'file';
else {
    echo json_encode(['ok' => false, 'text' => 'That file type is not supported.']);
    exit();
}

// Belt-and-suspenders: verify the actual content isn't a disguised executable.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$blockedMimes = ['application/x-httpd-php', 'application/x-sh', 'text/x-php', 'application/x-executable'];
if (in_array($mime, $blockedMimes, true)) {
    echo json_encode(['ok' => false, 'text' => 'That file type is not allowed.']);
    exit();
}

$uploadDir = __DIR__ . '/uploads/community/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$safeName = bin2hex(random_bytes(16)) . '.' . $ext;
$destPath = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'text' => 'Could not save the file — please try again.']);
    exit();
}

$relPath = 'uploads/community/' . $safeName;
$originalName = ($type === 'audio') ? 'Voice message' : basename($file['name']);

$stmt = $conn->prepare("INSERT INTO community_messages (group_id, user_id, body, attachment_path, attachment_type, attachment_name) VALUES (?, ?, '', ?, ?, ?)");
$stmt->bind_param('iisss', $groupId, $user_id, $relPath, $type, $originalName);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();

$nameRes = $conn->prepare("SELECT name, is_premium FROM users WHERE id = ?");
$nameRes->bind_param('i', $user_id);
$nameRes->execute();
$nameRow = $nameRes->get_result()->fetch_assoc() ?: ['name' => 'You', 'is_premium' => 0];
$nameRes->close();

echo json_encode([
    'ok' => true,
    'message' => [
        'id' => $newId,
        'user_id' => $user_id,
        'name' => $nameRow['name'],
        'premium' => (bool) $nameRow['is_premium'],
        'body' => '',
        'attachment_path' => $relPath,
        'attachment_type' => $type,
        'attachment_name' => $originalName,
        'created_at' => date('c'),
    ],
]);
