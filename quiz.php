<?php
session_start();
include 'config/db.php';
$active = 'quiz';
$bank = include 'includes/quiz_bank.php';
$exams = include 'includes/exam_goals.php';
$subjectNames = array_keys($bank);
$prefillSubject = isset($_GET['subject']) ? trim($_GET['subject']) : '';

$prefillExam = 'OTHER';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT exam_goal FROM users WHERE id = ?");
    $uid = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($r['exam_goal'])) $prefillExam = $r['exam_goal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Quiz</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=2" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1526">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudyFlow">
<link rel="apple-touch-icon" href="assets/img/icons/apple-touch-icon.png">
</head>
<body>
<script>
(function(){
  var t = localStorage.getItem('sf-theme') || 'dark';
  if (t === 'dark') document.body.classList.add('sf-theme-deep');
})();
</script>
<?php include 'includes/navbar.php'; ?>

<section class="sf-section">
    <h2>Test yourself — timed, scored, ranked</h2>

    <!-- SETUP -->
    <div class="sf-feature-card mb-4" id="setupPanel" style="max-width:560px;">
        <h6 class="mb-3">Set up your quiz</h6>

        <div class="mb-3">
            <label class="form-label">Exam</label>
            <select id="examSelect" class="form-select">
                <?php foreach ($exams as $code => $label): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $prefillExam ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Questions are framed in this exam's style where relevant.</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Subject</label>
            <input list="subjectList" id="subjectInput" class="form-control" placeholder="Type any subject — e.g. Mass Communication"
                   value="<?php echo htmlspecialchars($prefillSubject); ?>">
            <datalist id="subjectList">
                <?php foreach ($subjectNames as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>">
                <?php endforeach; ?>
            </datalist>
            <small class="text-muted">Pick one from the list or type any subject in the world — questions are generated for it.</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Difficulty</label>
            <div class="d-flex gap-2 flex-wrap" id="difficultyPicker">
                <button type="button" class="sf-pill active" data-diff="Easy">Easy</button>
                <button type="button" class="sf-pill" data-diff="Intermediate">Intermediate</button>
                <button type="button" class="sf-pill" data-diff="Hard">Hard</button>
                <button type="button" class="sf-pill" data-diff="Scholar">Scholar</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Number of questions</label>
            <select id="qCountSelect" class="form-select">
                <option value="5">5 questions (30 sec)</option>
                <option value="10" selected>10 questions (1 min)</option>
                <option value="15">15 questions (1 min 30 sec)</option>
                <option value="20">20 questions (2 min)</option>
            </select>
        </div>
        <button class="btn-sf-gold" style="margin:0;" id="startQuizBtn">Start quiz</button>
        <div id="loadingMsg" class="text-muted mt-2" style="display:none;">Building your questions…</div>
        <div id="errorMsg" class="mt-2" style="display:none;color:var(--terracotta);"></div>
    </div>

    <!-- QUIZ AREA -->
    <div class="row g-4" id="quizArea" style="display:none;">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">Question <span id="qNum">1</span> of <span id="qTotal">5</span></span>
                <span class="sf-timer" id="timer">00:30</span>
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

        <div class="col-lg-4">
            <h6 class="sf-section-eyebrow">Live leaderboard</h6>
            <div class="sf-feature-card p-0" id="leaderboard"></div>
        </div>
    </div>

    <div class="sf-feature-card mt-3" id="resultPanel" style="display:none;max-width:520px;">
        <h5 class="mb-2">Quiz complete!</h5>
        <p>You scored <strong id="finalScore">0</strong> points. <span id="xpGainedNote" style="color:var(--gold-light);font-weight:600;"></span></p>
        <button class="btn-sf-gold" style="margin:0;" id="retakeBtn">Set up another quiz</button>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const bank = <?php echo json_encode($bank); ?>;
let questions = [], i = 0, score = 0, correctCount = 0, timeLeft = 30, timerInt, currentSubject = '', currentDifficulty = 'Easy', advancing = false;

document.querySelectorAll('#difficultyPicker .sf-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#difficultyPicker .sf-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});

function shuffle(arr){ return arr.map(v=>[Math.random(),v]).sort((a,b)=>a[0]-b[0]).map(v=>v[1]); }

function renderLeaderboard(rows, you) {
    const box = document.getElementById('leaderboard');
    let combined = rows.map(r => ({ name: r.name, score: r.score, premium: r.premium, mine: false }));
    if (you) {
        const idx = combined.findIndex(r => r.mine === false && r.score === you.score);
        combined.push({ name: 'You', score: you.score, premium: false, mine: true });
    }
    combined.sort((a,b) => b.score - a.score);
    if (combined.length === 0) {
        box.innerHTML = '<div class="p-3 text-muted small">No scores yet for this subject — be the first!</div>';
        return;
    }
    box.innerHTML = combined.map((r, idx) => `
        <div class="sf-leaderboard-row" style="${r.mine ? 'background:var(--paper-2);' : ''}">
            <span><span class="sf-rank">#${idx+1}</span>${r.name}${r.premium ? ' <span title="Premium" style="color:var(--gold-dark);">★</span>' : ''}</span>
            <span class="mono">${r.score}</span>
        </div>`).join('');
}

function fetchLeaderboard(subject) {
    fetch('leaderboard.php?subject=' + encodeURIComponent(subject))
        .then(r => r.json())
        .then(d => { if (d.ok) renderLeaderboard(d.rows, d.you); });
}

function submitScore(subject, difficulty, finalScore, correct, total) {
    const fd = new FormData();
    fd.append('submit', '1');
    fd.append('subject', subject);
    fd.append('difficulty', difficulty);
    fd.append('score', finalScore);
    fd.append('correct', correct);
    fd.append('total', total);
    fetch('leaderboard.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) renderLeaderboard(d.rows, d.you);
            if (d.ok && typeof d.xp_gained !== 'undefined') {
                const xpNote = document.getElementById('xpGainedNote');
                if (xpNote) xpNote.innerText = '+' + d.xp_gained + ' XP earned';
            }
        });
}

document.getElementById('startQuizBtn').addEventListener('click', () => {
    const subject = document.getElementById('subjectInput').value.trim();
    const difficulty = document.querySelector('#difficultyPicker .sf-pill.active').dataset.diff;
    const exam = document.getElementById('examSelect').value;
    const count = parseInt(document.getElementById('qCountSelect').value);
    const errBox = document.getElementById('errorMsg');
    errBox.style.display = 'none';

    if (subject === '') { errBox.textContent = 'Type or pick a subject first.'; errBox.style.display = 'block'; return; }

    currentSubject = subject;
    currentDifficulty = difficulty;

    const bankKey = Object.keys(bank).find(k => k.toLowerCase() === subject.toLowerCase());

    if (bankKey && difficulty === 'Easy') {
        // Instant load from the local bank for known subjects at Easy level.
        const pool = shuffle(bank[bankKey]);
        questions = [];
        for (let k = 0; k < count; k++) questions.push(pool[k % pool.length]);
        beginQuiz(count);
        return;
    }

    // Otherwise generate fresh questions for ANY subject + difficulty via AI.
    document.getElementById('loadingMsg').style.display = 'block';
    document.getElementById('startQuizBtn').disabled = true;

    const fd = new FormData();
    fd.append('subject', subject);
    fd.append('difficulty', difficulty);
    fd.append('count', count);
    fd.append('exam', exam);

    fetch('quiz_generate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('loadingMsg').style.display = 'none';
            document.getElementById('startQuizBtn').disabled = false;
            if (!d.ok) {
                if (bankKey) {
                    // Fall back to the bank if AI generation fails.
                    const pool = shuffle(bank[bankKey]);
                    questions = [];
                    for (let k = 0; k < count; k++) questions.push(pool[k % pool.length]);
                    beginQuiz(count);
                    return;
                }
                errBox.textContent = d.text || 'Could not generate that quiz — try again.';
                errBox.style.display = 'block';
                return;
            }
            questions = d.questions.slice(0, count);
            beginQuiz(questions.length);
        })
        .catch(() => {
            document.getElementById('loadingMsg').style.display = 'none';
            document.getElementById('startQuizBtn').disabled = false;
            errBox.textContent = 'Network error generating the quiz — try again.';
            errBox.style.display = 'block';
        });
});

function beginQuiz(count) {
    i = 0; score = 0; correctCount = 0;
    timeLeft = Math.ceil(count / 5) * 30;

    document.getElementById('setupPanel').style.display = 'none';
    document.getElementById('resultPanel').style.display = 'none';
    document.getElementById('quizArea').style.display = 'flex';
    document.getElementById('qTotal').innerText = count;
    document.getElementById('score').innerText = 0;
    fetchLeaderboard(currentSubject);
    startWholeQuizTimer();
    loadQ();
}

function startWholeQuizTimer(){
    clearInterval(timerInt);
    updateTimerDisplay();
    timerInt = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0){ clearInterval(timerInt); finishQuiz(); }
    }, 1000);
}
function updateTimerDisplay(){
    const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
    const s = String(timeLeft%60).padStart(2,'0');
    document.getElementById('timer').innerText = `${m}:${s}`;
}

function loadQ(){
  advancing = false;
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
  if (advancing) return;
  advancing = true;
  const q = questions[i];
  document.querySelectorAll('.sf-quiz-option').forEach((b, bi) => {
    b.disabled = true;
    if (bi === q.correct) b.classList.add('correct');
    else if (bi === idx) b.classList.add('wrong');
  });
  if (idx === q.correct) { score += 20; correctCount++; document.getElementById('score').innerText = score; }

  const isLast = i >= questions.length - 1;
  document.getElementById('advanceHint').innerText = isLast ? 'Finishing…' : 'Next question…';

  // Auto-advance — no "Next" button needed.
  setTimeout(() => {
    if (isLast) { clearInterval(timerInt); finishQuiz(); }
    else { i++; loadQ(); }
  }, 900);
}
function finishQuiz(){
  clearInterval(timerInt);
  document.getElementById('quizArea').style.display = 'none';
  document.getElementById('finalScore').innerText = score;
  document.getElementById('resultPanel').style.display = 'block';
  submitScore(currentSubject, currentDifficulty, score, correctCount, questions.length);
}
document.getElementById('retakeBtn').onclick = () => {
  document.getElementById('resultPanel').style.display = 'none';
  document.getElementById('setupPanel').style.display = 'block';
};
</script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
</script>
</body>
</html>
