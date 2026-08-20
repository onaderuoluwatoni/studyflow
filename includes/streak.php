<?php
/**
 * Updates a user's streak on a rolling 34-HOUR WINDOW rather than a
 * calendar-day comparison. If the user comes back within 34 hours of
 * their last visit, the streak continues (and increments once per
 * fresh visit). If more than 34 hours pass with no visit, the streak
 * normally breaks — UNLESS the user has a Streak Shield banked, in
 * which case one shield is spent to protect it instead of resetting.
 * Shields are earned automatically every 14-day streak (max 3 banked)
 * and can never be bought — they're purely a reward for consistency.
 */
function sfUpdateStreakOnLogin($conn, $user_id, $timezone) {
    $timezone = $timezone ?: 'UTC';
    $user_id = (int) $user_id;

    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }

    $now = new DateTime('now', $tz);
    $today = $now->format('Y-m-d');
    $nowStr = $now->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT * FROM user_streaks WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $stmt = $conn->prepare("INSERT INTO user_streaks (user_id, last_active_date, last_active_at, streak_count, longest_streak, shields) VALUES (?, ?, ?, 1, 1, 0)");
        $stmt->bind_param('iss', $user_id, $today, $nowStr);
        $stmt->execute();
        $stmt->close();
        // First visit ever — no daily reward popup yet, that starts from the next visit.
        return false;
    }

    if ($row['last_active_date'] === $today) {
        // Already counted today — just refresh the timestamp so the
        // 34-hour window keeps sliding from this visit.
        $stmt = $conn->prepare("UPDATE user_streaks SET last_active_at = ? WHERE user_id = ?");
        $stmt->bind_param('si', $nowStr, $user_id);
        $stmt->execute();
        $stmt->close();
        return false;
    }

    $lastActiveAt = $row['last_active_at'] ?? ($row['last_active_date'] . ' 00:00:00');
    try {
        $last = new DateTime($lastActiveAt, $tz);
    } catch (Exception $e) {
        $last = new DateTime('1970-01-01', $tz);
    }

    $hoursSince = ($now->getTimestamp() - $last->getTimestamp()) / 3600;
    $shields = (int) $row['shields'];
    $streak = (int) $row['streak_count'];
    $longest = (int) $row['longest_streak'];

    if ($hoursSince <= 34) {
        // Back within the window — streak continues and ticks up.
        $streak = $streak + 1;
    } elseif ($shields > 0) {
        // Missed the window, but a banked shield protects the streak.
        $shields -= 1;
        $streak = $streak + 1;
    } else {
        // Gone too long with no shield available — streak resets.
        $streak = 1;
    }

    // Earn one shield every 14-day milestone, capped at 3 banked.
    if ($streak > 0 && $streak % 14 === 0 && $shields < 3) {
        $shields += 1;
    }

    $longest = max($longest, $streak);

    $stmt = $conn->prepare("
        UPDATE user_streaks
        SET last_active_date = ?, last_active_at = ?, streak_count = ?, longest_streak = ?, shields = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param('ssiiii', $today, $nowStr, $streak, $longest, $shields, $user_id);
    $stmt->execute();
    $stmt->close();

    // A returning visit on a fresh calendar day (not their very first ever) — show the daily reward popup.
    return true;
}
