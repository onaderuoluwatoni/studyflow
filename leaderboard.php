<?php
session_start();
include 'config/db.php';
include 'includes/coach.php';
header('Content-Type: application/json');

$subject = trim($_REQUEST['subject'] ?? 'General');
$subjectEsc = $conn->real_escape_string($subject);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'text' => 'Please sign in to save your score.']);
        exit();
    }
    $user_id = (int) $_SESSION['user_id'];
    $difficulty = trim($_POST['difficulty'] ?? 'Easy');
    if (!in_array($difficulty, ['Easy', 'Hard', 'Scholar'], true)) {
        $difficulty = 'Easy';
    }
    $score = (int) ($_POST['score'] ?? 0);
    $correct = max(0, (int) ($_POST['correct'] ?? 0));
    $total = max(1, (int) ($_POST['total'] ?? 1));
    if ($correct > $total) $correct = $total; // guard against tampered values

    $stmt = $conn->prepare("INSERT INTO quiz_scores (user_id, subject, difficulty, score) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('issi', $user_id, $subject, $difficulty, $score);
    $stmt->execute();
    $stmt->close();

    // XP is capped per difficulty and scaled by how many were actually right —
    // a full 20-question quiz should never be worth more than a small handful of XP.
    $xpCapByDifficulty = ['Easy' => 4, 'Hard' => 7, 'Scholar' => 10];
    $cap = $xpCapByDifficulty[$difficulty] ?? 4;
    $xpGained = $correct > 0 ? max(1, (int) round($cap * ($correct / $total))) : 0;

    if ($xpGained > 0) {
        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->bind_param('ii', $xpGained, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Count this toward today's daily challenge, if it's a quiz challenge.
    sfAdvanceDailyChallenge($conn, $user_id, 'quiz', 1);
}

// Best score per user for this subject, top 8, "You" merged in even if not in top 8.
$rows = [];
$res = $conn->query("
    SELECT u.id, u.name, u.is_premium, MAX(qs.score) AS best
    FROM quiz_scores qs
    JOIN users u ON u.id = qs.user_id
    WHERE qs.subject = '$subjectEsc'
    GROUP BY u.id, u.name, u.is_premium
    ORDER BY best DESC
    LIMIT 8
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'premium' => (bool) $row['is_premium'],
            'score' => (int) $row['best'],
        ];
    }
}

$you = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $r = $conn->query("SELECT MAX(score) AS best FROM quiz_scores WHERE subject='$subjectEsc' AND user_id='$uid'");
    if ($r && $r->num_rows > 0) {
        $best = (int) ($r->fetch_assoc()['best'] ?? 0);
        $you = ['id' => $uid, 'score' => $best];
    }
}

echo json_encode(['ok' => true, 'rows' => $rows, 'you' => $you, 'xp_gained' => $xpGained ?? null]);
