<?php
/**
 * Shared username rules so the live-check, registration, and profile
 * editing all enforce the exact same thing — no bypassing the fuzzy
 * near-duplicate check by skipping the AJAX preview.
 */

function sfUsernameFormatValid($username) {
    return preg_match('/^[a-zA-Z0-9_.]{3,20}$/', $username)
        && !preg_match('/^\.|\.$/', $username)
        && strpos($username, '..') === false
        && preg_match('/[_.]/', $username);
}

/**
 * True if $username is exactly taken, or close enough to an existing
 * username (edit distance <= 1 on the letters/digits only) that it could
 * pass as an impersonation attempt. $excludeUserId lets a user keep their
 * own current username when re-saving unrelated profile fields.
 */
function sfUsernameUnavailable($conn, $username, $excludeUserId = null) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username IS NOT NULL" . ($excludeUserId ? " AND id != ?" : ""));
    if ($excludeUserId) {
        $stmt->bind_param('i', $excludeUserId);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $stripped = strtolower(preg_replace('/[^a-z0-9]/i', '', $username));

    while ($row = $res->fetch_assoc()) {
        if ($row['username'] === $username) return true; // exact match

        $existingStripped = strtolower(preg_replace('/[^a-z0-9]/i', '', $row['username']));
        if ($existingStripped === '' || $stripped === '') continue;
        if (abs(strlen($existingStripped) - strlen($stripped)) > 2) continue;

        if (levenshtein($stripped, $existingStripped) <= 1) return true;
    }
    $stmt->close();
    return false;
}
