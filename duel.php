<?php
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$duelId = (int) ($_GET['id'] ?? 0);
$active = 'friends';

$stmt = $conn->prepare("
    SELECT fd.*, uc.name AS challenger_name, uo.name AS opponent_name
    FROM friend_duels fd
    JOIN users uc ON uc.id = fd.challenger_id
    JOIN users uo ON uo.id = fd.opponent_id
    WHERE fd.id = ? AND (fd.challenger_id = ? OR fd.opponent_id = ?)
");
$stmt->bind_param('iii', $duelId, $user_id, $user_id);
$stmt->execute();
$duel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$duel) {
    header("Location: friends.php");
    exit();
}

$isChallenger = ((int) $duel['challenger_id'] === $user_id);
$opponentName = $isChallenger ? $duel['opponent_name'] : $duel['challenger_name'];
$iAlreadyFinished = $isChallenger ? (bool) $duel['challenger_finished'] : (bool) $duel['opponent_finished'];
$questions = $duel['questions_json'] ? json_decode($duel['questions_json'], true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Duel vs <?php echo htmlspecialchars($opponentName); ?></title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=2" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1526">
<link rel="apple-touch-icon" href="assets/img/icons/apple-touch-icon.png">
</head>
<body>
<script>
(function(){ var t = localStorage.getItem('sf-theme') || 'dark'; if (t === 'dark') document.body.classList.add('sf-theme-deep'); })();
</script>
<?php include 'includes/navbar.php'; ?>

<section class="sf-section">
    <h2>vs <?php echo htmlspecialchars($opponentName); ?> — <?php echo (int) $duel['stake']; ?> XP on the line</h2>

    <?php if ($duel['status'] === 'declined'): ?>
        <p class="text-muted">This duel was declined.</p>
        <a href="friends.php" class="btn-sf-gold" style="margin:0;">Back to Friends</a>

    <?php elseif ($duel['status'] === 'pending'): ?>
        <p class="text-muted">Waiting for <?php echo htmlspecialchars($opponentName); ?> to accept...</p>
        <a href="friends.php" class="btn-sf-gold" style="margin:0;">Back to Friends</a>

    <?php elseif ($iAlreadyFinished || $duel['status'] === 'finished'): ?>
        <div id="resultBox" class="sf-feature-card" style="max-width:480px;">
            <p class="text-muted">Loading result...</p>
        </div>

    <?php else: ?>
        <!-- QUIZ AREA -->
        <div class="row g-4" id="quizArea">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Question <span id="qNum">1</span> of <span id="qTotal"><?php echo count($questions); ?></span></span>
                    <span class="sf-timer" id="timer">00:00</span>
                </div>
                <div class="sf-feature-card">
                    <h5 id="qText">Loading question...</h5>
                    <div id="qOptions" class="mt-3"></div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <span>Score: <strong id="score">0</strong></span>
                    <span class="text-muted small" id="advanceHint"></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const duelId = <?php echo (int) $duelId; ?>;
const questions = <?php echo json_encode($questions); ?>;
let i = 0, score = 0, timeLeft = <?php echo (int) ($duel['time_limit_seconds'] ?? 120); ?>, startTime = Date.now(), timerInt;

function updateTimerDisplay(){
    const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
    const s = String(timeLeft%60).padStart(2,'0');
    const el = document.getElementById('timer');
    if (el) el.innerText = `${m}:${s}`;
}

function startDuelTimer(){
    clearInterval(timerInt);
    updateTimerDisplay();
    timerInt = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) { clearInterval(timerInt); finishDuel(); }
    }, 1000);
}

function loadQ(){
    const q = questions[i];
    document.getElementById('qNum').innerText = i+1;
    document.getElementById('qText').innerText = q.q;
    document.getElementById('advanceHint').innerText = '';
    const box = document.getElementById('qOptions');
    box.innerHTML = '';
    q.opts.forEach((o, idx) => {
        const btn = document.createElement('button');
        btn.className = 'sf-quiz-option';
        btn.innerText = o;
        btn.onclick = () => selectAnswer(idx);
        box.appendChild(btn);
    });
}

function selectAnswer(idx){
    const q = questions[i];
    document.querySelectorAll('.sf-quiz-option').forEach((b, bi) => {
        b.disabled = true;
        if (bi === q.correct) b.classList.add('correct');
        else if (bi === idx) b.classList.add('wrong');
    });
    if (idx === q.correct) { score += 1; document.getElementById('score').innerText = score; }

    const isLast = i >= questions.length - 1;
    document.getElementById('advanceHint').innerText = isLast ? 'Finishing…' : 'Next question…';

    setTimeout(() => {
        if (isLast) { clearInterval(timerInt); finishDuel(); }
        else { i++; loadQ(); }
    }, 700);
}

function finishDuel(){
    const timeSeconds = Math.max(1, Math.floor((Date.now() - startTime) / 1000));
    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('duel_id', duelId);
    fd.append('score', score);
    fd.append('time_seconds', timeSeconds);
    fetch('duel_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => {
            document.getElementById('quizArea').outerHTML = '<div id="resultBox" class="sf-feature-card" style="max-width:480px;"><p class="text-muted">Loading result...</p></div>';
            pollResult();
        });
}

function pollResult(){
    const fd = new FormData();
    fd.append('action', 'status');
    fd.append('duel_id', duelId);
    fetch('duel_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            const box = document.getElementById('resultBox');
            if (d.status !== 'finished') {
                box.innerHTML = `<p class="mb-2">You scored <strong>${d.my_score ?? score}</strong>.</p><p class="text-muted">Waiting for ${d.opponent_name} to finish...</p>`;
                setTimeout(pollResult, 3000);
                return;
            }
            let resultText;
            if (d.winner_id === null) {
                resultText = `<h5>It's a draw!</h5><p>No XP changed hands.</p>`;
            } else {
                const iWon = String(d.winner_id) === String(<?php echo json_encode($user_id); ?>);
                resultText = iWon
                    ? `<h5>🏆 You won!</h5><p>+${d.stake} XP</p>`
                    : `<h5>You lost this one.</h5><p>-${d.stake} XP — win it back next time.</p>`;
            }
            box.innerHTML = resultText + `<p class="text-muted small">You: ${d.my_score} correct · ${d.opponent_name}: ${d.opponent_score} correct</p>
                <a href="friends.php" class="btn-sf-gold" style="margin:0;">Back to Friends</a>`;
        });
}

if (questions && questions.length > 0 && document.getElementById('quizArea')) {
    loadQ();
    startDuelTimer();
} else if (document.getElementById('resultBox')) {
    pollResult();
}
</script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('service-worker.js').catch(() => {}); });
}
</script>
</body>
</html>
