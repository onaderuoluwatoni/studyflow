<?php
/**
 * Friend system helpers. A "friendship" row can be:
 * - pending: requester_id sent a request to addressee_id, not yet answered
 * - accepted: both are friends
 * - blocked: requester_id has blocked addressee_id (blocker is always requester_id)
 */

function sfFriendStatus($conn, $userA, $userB) {
    $stmt = $conn->prepare("SELECT requester_id, addressee_id, status FROM friendships
                             WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)
                             LIMIT 1");
    $stmt->bind_param('iiii', $userA, $userB, $userB, $userA);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row; // null if no relationship yet
}

function sfGetFriends($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.xp, u.exam_goal, u.avatar
        FROM friendships f
        JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
        WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted'
        ORDER BY u.name ASC
    ");
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function sfGetPendingRequests($conn, $user_id) {
    // Requests sent TO this user, still pending.
    $stmt = $conn->prepare("
        SELECT f.id AS friendship_id, u.id, u.name, u.exam_goal
        FROM friendships f
        JOIN users u ON u.id = f.requester_id
        WHERE f.addressee_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function sfGetUserStats($conn, $user_id) {
    $stats = ['xp' => 0, 'coins' => 0, 'exam_goal' => '', 'streak' => 0, 'longest_streak' => 0, 'quiz_avg' => 0, 'quiz_count' => 0];

    $stmt = $conn->prepare("SELECT xp, coins, exam_goal FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($u) {
        $stats['xp'] = (int) $u['xp'];
        $stats['coins'] = (int) $u['coins'];
        $stats['exam_goal'] = $u['exam_goal'];
    }

    $stmt = $conn->prepare("SELECT streak_count, longest_streak FROM user_streaks WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($s) {
        $stats['streak'] = (int) $s['streak_count'];
        $stats['longest_streak'] = max((int) $s['streak_count'], (int) $s['longest_streak']);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c, AVG(score) AS avg_score FROM quiz_scores WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $q = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $stats['quiz_count'] = (int) ($q['c'] ?? 0);
    $stats['quiz_avg'] = round((float) ($q['avg_score'] ?? 0));

    return $stats;
}

/** Progress toward a friend challenge, counted since the challenge started. */
function sfFriendChallengeProgress($conn, $user_id, $type, $since) {
    switch ($type) {
        case 'quiz':
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM quiz_scores WHERE user_id = ? AND created_at >= ?");
            break;
        case 'flashcard':
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM flashcards WHERE user_id = ? AND created_at >= ?");
            break;
        case 'task':
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tasks WHERE user_id = ? AND status = 'completed' AND due_date >= ?");
            break;
        default:
            return 0;
    }
    $stmt->bind_param('is', $user_id, $since);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

function sfFriendChallengeLabel($type) {
    $labels = [
        'quiz' => 'Most quizzes completed',
        'flashcard' => 'Most flashcards reviewed',
        'task' => 'Most planner tasks finished',
    ];
    return $labels[$type] ?? $type;
}
