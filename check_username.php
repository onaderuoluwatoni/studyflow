<?php
include 'config/db.php';
include 'includes/username_rules.php';
header('Content-Type: application/json');

$username = trim($_GET['username'] ?? $_POST['username'] ?? '');

if (!sfUsernameFormatValid($username)) {
    echo json_encode(['ok' => true, 'available' => false, 'reason' => '3–20 characters, letters/numbers only, and must include at least one "." or "_". Can\'t start or end with a period.']);
    exit();
}

if (sfUsernameUnavailable($conn, $username)) {
    echo json_encode(['ok' => true, 'available' => false, 'reason' => 'Unavailable — taken or too similar to an existing username.']);
    exit();
}

echo json_encode(['ok' => true, 'available' => true, 'reason' => '']);
