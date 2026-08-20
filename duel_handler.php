<?php
session_start();
include 'config/db.php';
include 'config/ai.php';
include 'includes/friends.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

function sfMyXp($conn, $uid) {
    $stmt = $conn->prepare("SELECT xp FROM users WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $xp = (int) ($stmt->get_result()->fetch_assoc()['xp'] ?? 0);
    $stmt->close();
    return $xp;
}

if ($action === 'create') {
    $friendId = (int) ($_POST['friend_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $difficulty = trim($_POST['difficulty'] ?? 'Easy');
    $count = max(3, min(15, (int) ($_POST['count'] ?? 5)));
    $stake = max(5, min(20, (int) ($_POST['stake'] ?? 10)));
    $timeLimit = (int) ($_POST['time_limit'] ?? 120);
    if (!in_array($timeLimit, [60, 120, 180, 300], true)) $timeLimit = 120;

    if ($subject === '') {
        echo json_encode(['ok' => false, 'text' => 'Pick a subject for the duel.']);
        exit();
    }

    $rel = sfFriendStatus($conn, $user_id, $friendId);
    if (!$rel || $rel['status'] !== 'accepted') {
        echo json_encode(['ok' => false, 'text' => 'You can only duel a friend.']);
        exit();
    }

    if (sfMyXp($conn, $user_id) < $stake) {
        echo json_encode(['ok' => false, 'text' => "You need at least {$stake} XP to stake that much. You currently have " . sfMyXp($conn, $user_id) . " XP."]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO friend_duels (challenger_id, opponent_id, subject, difficulty, question_count, stake, time_limit_seconds, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('iissiii', $user_id, $friendId, $subject, $difficulty, $count, $stake, $timeLimit);
    $stmt->execute();
    $newDuelId = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['ok' => true, 'text' => 'Duel invite sent!', 'duelId' => $newDuelId]);
    exit();
}

if ($action === 'decline') {
    $duelId = (int) ($_POST['duel_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE friend_duels SET status = 'declined' WHERE id = ? AND opponent_id = ? AND status = 'pending'");
    $stmt->bind_param('ii', $duelId, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'accept') {
    $duelId = (int) ($_POST['duel_id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM friend_duels WHERE id = ? AND opponent_id = ? AND status = 'pending'");
    $stmt->bind_param('ii', $duelId, $user_id);
    $stmt->execute();
    $duel = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$duel) {
        echo json_encode(['ok' => false, 'text' => 'That duel is no longer available.']);
        exit();
    }

    $stake = (int) $duel['stake'];
    $myXp = sfMyXp($conn, $user_id);
    $challengerXp = sfMyXp($conn, (int) $duel['challenger_id']);

    if ($myXp < $stake) {
        echo json_encode(['ok' => false, 'text' => "Insufficient XP — this duel needs {$stake} XP and you have {$myXp}."]);
        exit();
    }
    if ($challengerXp < $stake) {
        // The challenger's balance dropped below the stake since they sent the invite.
        echo json_encode(['ok' => false, 'text' => 'The other player no longer has enough XP to cover this stake.']);
        exit();
    }

    // Generate the shared question set the two players will both answer.
    $difficultyGuide = [
        'Easy' => 'basic recall / definitions',
        'Intermediate' => 'applied understanding, WAEC/NECO/JAMB level',
        'Hard' => 'analytical, university-level',
        'Scholar' => 'advanced, postgraduate-level',
    ];
    $guide = $difficultyGuide[$duel['difficulty']] ?? $difficultyGuide['Easy'];
    $seed = bin2hex(random_bytes(4)); // forces a fresh generation instead of a cached-feeling repeat
    $prompt = "Write exactly {$duel['question_count']} multiple-choice quiz questions on \"{$duel['subject']}\" at {$duel['difficulty']} difficulty ({$guide}). "
        . "Cover a wide, varied spread of sub-topics within the subject rather than clustering around one narrow area — no two questions should test the same fact. "
        . "Each question needs exactly 4 options with one correct. Session reference: {$seed}. Respond with ONLY raw JSON: "
        . '[{"q":"...","opts":["a","b","c","d"],"correct":0}]';
    $result = callAI($prompt, 'You are a question-bank generator. Output only valid JSON, no markdown fences, no commentary.');

    $questions = null;
    if ($result['ok']) {
        $text = trim(preg_replace('/^```(json)?|```$/i', '', trim($result['text'])));
        $parsed = json_decode($text, true);
        if (is_array($parsed) && count($parsed) > 0) {
            $clean = [];
            foreach ($parsed as $q) {
                if (!isset($q['q'], $q['opts'], $q['correct']) || !is_array($q['opts'])) continue;
                $clean[] = ['q' => (string) $q['q'], 'opts' => array_map('strval', $q['opts']), 'correct' => (int) $q['correct']];
            }
            if (count($clean) > 0) $questions = $clean;
        }
    }

    if (!$questions) {
        // Fall back to the local bank if AI generation fails.
        $bank = include 'includes/quiz_bank.php';
        $bankKey = null;
        foreach (array_keys($bank) as $k) {
            if (strcasecmp($k, $duel['subject']) === 0) { $bankKey = $k; break; }
        }
        if ($bankKey) {
            $pool = $bank[$bankKey];
            shuffle($pool);
            $questions = array_slice($pool, 0, (int) $duel['question_count']);
        }
    }

    if (!$questions || count($questions) === 0) {
        echo json_encode(['ok' => false, 'text' => 'Could not generate duel questions for that subject right now — try again shortly.']);
        exit();
    }

    $questionsJson = json_encode($questions);
    $stmt = $conn->prepare("UPDATE friend_duels SET status = 'active', questions_json = ?, accepted_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $questionsJson, $duelId);
    $stmt->execute();
    $stmt->close();

    // Escrow the stake from both players now that the duel is actually starting —
    // this XP is genuinely at risk for both sides until it resolves.
    $negStake = -$stake;
    $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
    $stmt->bind_param('ii', $negStake, $duel['challenger_id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
    $stmt->bind_param('ii', $negStake, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'duel_id' => $duelId]);
    exit();
}

if ($action === 'submit') {
    $duelId = (int) ($_POST['duel_id'] ?? 0);
    $score = max(0, (int) ($_POST['score'] ?? 0));
    $timeSeconds = max(1, (int) ($_POST['time_seconds'] ?? 9999));

    $stmt = $conn->prepare("SELECT * FROM friend_duels WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $duelId);
    $stmt->execute();
    $duel = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$duel) {
        echo json_encode(['ok' => false, 'text' => 'Duel not found or already finished.']);
        exit();
    }

    $isChallenger = ((int) $duel['challenger_id'] === $user_id);
    $isOpponent = ((int) $duel['opponent_id'] === $user_id);
    if (!$isChallenger && !$isOpponent) {
        echo json_encode(['ok' => false, 'text' => 'Not your duel.']);
        exit();
    }

    if ($isChallenger && !$duel['challenger_finished']) {
        $stmt = $conn->prepare("UPDATE friend_duels SET challenger_score = ?, challenger_time_seconds = ?, challenger_finished = 1 WHERE id = ?");
        $stmt->bind_param('iii', $score, $timeSeconds, $duelId);
        $stmt->execute();
        $stmt->close();
    } elseif ($isOpponent && !$duel['opponent_finished']) {
        $stmt = $conn->prepare("UPDATE friend_duels SET opponent_score = ?, opponent_time_seconds = ?, opponent_finished = 1 WHERE id = ?");
        $stmt->bind_param('iii', $score, $timeSeconds, $duelId);
        $stmt->execute();
        $stmt->close();
    }

    // Re-fetch to see if both sides are now done.
    $stmt = $conn->prepare("SELECT * FROM friend_duels WHERE id = ?");
    $stmt->bind_param('i', $duelId);
    $stmt->execute();
    $duel = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($duel['challenger_finished'] && $duel['opponent_finished'] && $duel['status'] === 'active') {
        sfResolveDuel($conn, $duel);
    }

    echo json_encode(['ok' => true]);
    exit();
}

if ($action === 'status') {
    $duelId = (int) ($_POST['duel_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT fd.*, uc.name AS challenger_name, uo.name AS opponent_name
        FROM friend_duels fd
        JOIN users uc ON uc.id = fd.challenger_id
        JOIN users uo ON uo.id = fd.opponent_id
        WHERE fd.id = ?
    ");
    $stmt->bind_param('i', $duelId);
    $stmt->execute();
    $duel = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$duel) {
        echo json_encode(['ok' => false, 'text' => 'Duel not found.']);
        exit();
    }

    $isChallenger = ((int) $duel['challenger_id'] === $user_id);
    echo json_encode([
        'ok' => true,
        'status' => $duel['status'],
        'my_finished' => (bool) ($isChallenger ? $duel['challenger_finished'] : $duel['opponent_finished']),
        'opponent_finished' => (bool) ($isChallenger ? $duel['opponent_finished'] : $duel['challenger_finished']),
        'opponent_name' => $isChallenger ? $duel['opponent_name'] : $duel['challenger_name'],
        'winner_id' => $duel['winner_id'] ? (int) $duel['winner_id'] : null,
        'stake' => (int) $duel['stake'],
        'my_score' => $isChallenger ? $duel['challenger_score'] : $duel['opponent_score'],
        'opponent_score' => $isChallenger ? $duel['opponent_score'] : $duel['challenger_score'],
    ]);
    exit();
}

echo json_encode(['ok' => false, 'text' => 'Unknown action.']);

/**
 * Resolves a finished duel: higher score wins, faster time breaks a tie.
 * Both stakes were already escrowed (deducted from both players) when the
 * duel went active, so here the winner collects the full pot (2x stake —
 * their own stake back plus the loser's), the loser simply doesn't get
 * their stake back, and a true draw refunds both players their own stake.
 */
function sfResolveDuel($conn, $duel) {
    $duelId = (int) $duel['id'];
    $stake = (int) $duel['stake'];

    $cScore = (int) $duel['challenger_score'];
    $oScore = (int) $duel['opponent_score'];
    $cTime = (int) $duel['challenger_time_seconds'];
    $oTime = (int) $duel['opponent_time_seconds'];

    $winnerId = null;
    if ($cScore > $oScore) $winnerId = (int) $duel['challenger_id'];
    elseif ($oScore > $cScore) $winnerId = (int) $duel['opponent_id'];
    elseif ($cTime < $oTime) $winnerId = (int) $duel['challenger_id'];
    elseif ($oTime < $cTime) $winnerId = (int) $duel['opponent_id'];
    // else: true draw, $winnerId stays null

    if ($winnerId !== null) {
        $pot = $stake * 2;
        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->bind_param('ii', $pot, $winnerId);
        $stmt->execute();
        $stmt->close();
        // Loser's stake was already removed at escrow time — nothing further happens to them.
    } else {
        // Draw — give each player their own stake back.
        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->bind_param('ii', $stake, $duel['challenger_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->bind_param('ii', $stake, $duel['opponent_id']);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE friend_duels SET status = 'finished', winner_id = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $winnerId, $duelId);
    $stmt->execute();
    $stmt->close();
}
