<?php
/**
 * StudyFlow "Coach" logic — dashboard progress features.
 * Everything here is computed directly from data already in the
 * database. No AI calls, so it's instant and costs nothing on every
 * dashboard load.
 */

/**
 * Weakest subjects from quiz_scores: average score per subject,
 * lowest first. Only considers subjects with at least 1 attempt.
 */
function sfGetWeakSubjects($conn, $user_id, $limit = 3) {
    $stmt = $conn->prepare("
        SELECT subject, AVG(score) AS avg_score, COUNT(*) AS attempts
        FROM quiz_scores
        WHERE user_id = ?
        GROUP BY subject
        HAVING attempts >= 1
        ORDER BY avg_score ASC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = ['subject' => $r['subject'], 'avg_score' => round((float) $r['avg_score']), 'attempts' => (int) $r['attempts']];
    }
    $stmt->close();
    return $rows;
}

/**
 * Builds a short, personal "Study Coach" message from real signals:
 * streak, weakest subject, and days since last task activity.
 * Returns a plain string — no AI call involved.
 */
function sfGetCoachMessage($conn, $user_id, $name) {
    $messages = [];

    // Streak signal
    $stmt = $conn->prepare("SELECT streak_count, shields FROM user_streaks WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $streakRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $streak = $streakRow ? (int) $streakRow['streak_count'] : 0;

    if ($streak >= 2) {
        $messages[] = "You're on a {$streak}-day streak — keep it going.";
    } elseif ($streak === 1) {
        $messages[] = "You started a new streak today. Come back tomorrow to build on it.";
    } else {
        $messages[] = "No active streak right now — one study session today gets it started.";
    }

    // Weakness signal
    $weak = sfGetWeakSubjects($conn, $user_id, 1);
    if (!empty($weak)) {
        $w = $weak[0];
        if ($w['avg_score'] < 60) {
            $messages[] = "Your average in {$w['subject']} is {$w['avg_score']}% — worth another quiz today.";
        }
    }

    // Pending tasks signal
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tasks WHERE user_id = ? AND status = 'pending' AND due_date <= CURDATE()");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $pending = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    if ($pending > 0) {
        $messages[] = "You have {$pending} planner " . ($pending === 1 ? "task" : "tasks") . " due or overdue.";
    }

    return $messages;
}

/**
 * Gets (creating if needed) today's daily challenge for a user.
 * Challenge type rotates based on the date so it feels fresh.
 */
function sfGetOrCreateDailyChallenge($conn, $user_id) {
    $today = date('Y-m-d');

    $stmt = $conn->prepare("SELECT * FROM daily_challenges WHERE user_id = ? AND challenge_date = ?");
    $stmt->bind_param('is', $user_id, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) return $row;

    $types = [
        ['type' => 'quiz',      'target' => 1,  'xp' => 30, 'label' => 'Complete 1 quiz'],
        ['type' => 'flashcard', 'target' => 10, 'xp' => 20, 'label' => 'Review 10 flashcards'],
        ['type' => 'task',      'target' => 1,  'xp' => 15, 'label' => 'Finish 1 planner task'],
        ['type' => 'pomodoro',  'target' => 1,  'xp' => 20, 'label' => 'Complete 1 Pomodoro session'],
    ];
    $pick = $types[(int) date('N') % count($types)]; // rotates by day of week

    $stmt = $conn->prepare("INSERT INTO daily_challenges (user_id, challenge_date, challenge_type, target_count, xp_awarded) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issii', $user_id, $today, $pick['type'], $pick['target'], $pick['xp']);
    $stmt->execute();
    $stmt->close();

    return [
        'challenge_type' => $pick['type'],
        'target_count' => $pick['target'],
        'progress_count' => 0,
        'completed' => 0,
        'xp_awarded' => $pick['xp'],
    ];
}

function sfDailyChallengeLabel($type) {
    $labels = [
        'quiz' => 'Complete 1 quiz',
        'flashcard' => 'Review 10 flashcards',
        'task' => 'Finish 1 planner task',
        'pomodoro' => 'Complete 1 Pomodoro session',
    ];
    return $labels[$type] ?? 'Study something today';
}

/**
 * Advances progress on today's daily challenge for a given type and
 * marks it completed + awards XP once the target is hit. Call this
 * from wherever the matching action happens (quiz submit, task
 * complete, etc).
 */
function sfAdvanceDailyChallenge($conn, $user_id, $type, $amount = 1) {
    $today = date('Y-m-d');
    $challenge = sfGetOrCreateDailyChallenge($conn, $user_id);

    if (($challenge['challenge_type'] ?? '') !== $type) return; // today's challenge isn't this type
    if (!empty($challenge['completed'])) return; // already done

    $newProgress = (int) $challenge['progress_count'] + $amount;
    $target = (int) $challenge['target_count'];
    $completedNow = $newProgress >= $target;

    $stmt = $conn->prepare("UPDATE daily_challenges SET progress_count = ?, completed = ? WHERE user_id = ? AND challenge_date = ?");
    $completedInt = $completedNow ? 1 : 0;
    $stmt->bind_param('iiis', $newProgress, $completedInt, $user_id, $today);
    $stmt->execute();
    $stmt->close();

    if ($completedNow) {
        $xp = (int) $challenge['xp_awarded'];
        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->bind_param('ii', $xp, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Computes earned badges from real data, persists any newly-earned
 * ones to user_badges, and awards coins the first time a badge is
 * earned (never again for the same badge). Returns an array of
 * ['icon' => ..., 'label' => ..., 'key' => ...] for all earned badges.
 */
function sfGetBadges($conn, $user_id) {
    $catalog = [];

    $stmt = $conn->prepare("SELECT streak_count, longest_streak FROM user_streaks WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $streakRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $longest = $streakRow ? max((int) $streakRow['streak_count'], (int) $streakRow['longest_streak']) : 0;
    if ($longest >= 7)  $catalog['streak7']  = ['icon' => '🔥', 'label' => '7-Day Streak', 'coins' => 30];
    if ($longest >= 30) $catalog['streak30'] = ['icon' => '🔥', 'label' => '30-Day Streak', 'coins' => 100];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c, AVG(score) AS avg_score FROM quiz_scores WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $q = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $quizCount = (int) ($q['c'] ?? 0);
    $avgScore = (float) ($q['avg_score'] ?? 0);
    if ($quizCount >= 1) $catalog['firstquiz'] = ['icon' => '📚', 'label' => 'First Quiz', 'coins' => 10];
    if ($quizCount >= 20 && $avgScore >= 80) $catalog['quizmaster'] = ['icon' => '🧠', 'label' => 'Quiz Master', 'coins' => 75];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_messages WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $msgCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    if ($msgCount >= 10) $catalog['communityhelper'] = ['icon' => '🤝', 'label' => 'Community Helper', 'coins' => 40];

    // Persist any newly-earned badges and award coins on first earn only.
    $stmt = $conn->prepare("SELECT badge_key FROM user_badges WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $already = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $already[$r['badge_key']] = true;
    $stmt->close();

    $badges = [];
    foreach ($catalog as $key => $b) {
        $badges[] = ['icon' => $b['icon'], 'label' => $b['label'], 'key' => $key];
        if (!isset($already[$key])) {
            $ins = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_key) VALUES (?, ?)");
            $ins->bind_param('is', $user_id, $key);
            $ins->execute();
            $ins->close();
            $coinAward = $b['coins'];
            $upd = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
            $upd->bind_param('ii', $coinAward, $user_id);
            $upd->execute();
            $upd->close();
        }
    }

    return $badges;
}
