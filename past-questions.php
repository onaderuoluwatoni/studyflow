<?php
session_start();
include 'config/db.php';
$active = 'pastquestions';

$bank = include 'includes/past_questions_bank.php';
$bankSubjects = array_keys($bank);
$universities = include 'includes/nigerian_universities.php';

$loggedIn = isset($_SESSION['user_id']);
$mySubjects = [];

if ($loggedIn) {
    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT subject_name FROM subjects WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $mySubjects[] = $row['subject_name'];
    }
    $stmt->close();
}

$allSubjectOptions = array_values(array_unique(array_merge($mySubjects, $bankSubjects)));
$prefillSubject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$exams = include 'includes/exam_goals.php';

$prefillExam = 'OTHER';
if ($loggedIn) {
    $stmt = $conn->prepare("SELECT exam_goal FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
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
<title>StudyFlow — Past Questions</title>
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
    <h2>Past questions — pick your subject, topic and year</h2>

    <?php if (!$loggedIn): ?>
        <div class="sf-feature-card" style="max-width:560px;">
            <p class="mb-2">Sign in to browse past questions for any subject.</p>
            <a href="register.php" class="btn-sf-gold" style="margin:0;">Start Learning</a>
        </div>
    <?php else: ?>

        <!-- TOGGLE / PICKER PANEL -->
        <div class="sf-feature-card mb-4" id="pqSetup" style="max-width:640px;">
            <h6 class="mb-3">Find your past questions</h6>

            <div class="mb-3">
                <label class="form-label">Exam</label>
                <select id="pqExam" class="form-select">
                    <?php foreach ($exams as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $prefillExam ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject</label>
                <input list="pqSubjectList" id="pqSubject" class="form-control" placeholder="Type or pick a subject" value="<?php echo htmlspecialchars($prefillSubject); ?>">
                <datalist id="pqSubjectList">
                    <?php foreach ($allSubjectOptions as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Level</label>
                    <select id="pqLevel" class="form-select">
                        <option value="Secondary">Secondary school</option>
                        <option value="University">University</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year <span class="text-muted">(optional)</span></label>
                    <select id="pqYear" class="form-select">
                        <option value="">Any year</option>
                        <?php for ($y = date('Y'); $y >= date('Y') - 14; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="mt-3" id="pqUniversityWrap" style="display:none;">
                <label class="form-label">School <span class="text-muted">(optional — Nigerian universities)</span></label>
                <select id="pqUniversity" class="form-select">
                    <option value="">Any / general</option>
                    <?php foreach ($universities as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mt-3">
                <label class="form-label">Topic <span class="text-muted">(optional)</span></label>
                <input type="text" id="pqTopic" class="form-control" placeholder="e.g. Quadratic Equations, Cell Biology...">
            </div>

            <button class="btn-sf-gold mt-3" style="margin:0;" id="pqGoBtn">Show past questions</button>
            <div id="pqLoading" class="text-muted mt-2" style="display:none;">Fetching past questions…</div>
            <div id="pqError" class="mt-2" style="display:none;color:var(--terracotta);"></div>
        </div>

        <!-- RESULTS -->
        <div id="pqResults" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-2" style="max-width:900px;">
                <h5 class="mb-0" id="pqResultTitle"></h5>
                <button class="sf-pill" id="pqChangeBtn" type="button">Change subject/topic/year</button>
            </div>
            <div class="row g-3" id="pqList"></div>
        </div>

    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const localBank = <?php echo json_encode($bank); ?>;

function findLocal(subject) {
    const key = Object.keys(localBank).find(k => k.toLowerCase() === subject.toLowerCase()
        || subject.toLowerCase().includes(k.toLowerCase()) || k.toLowerCase().includes(subject.toLowerCase()));
    return key ? localBank[key] : null;
}

function renderList(questions) {
    const box = document.getElementById('pqList');
    box.innerHTML = questions.map((q, i) => `
        <div class="col-md-6">
            <div class="sf-feature-card">
                <small class="text-muted mono">Q${i+1}</small>
                <p class="mb-0">${q}</p>
            </div>
        </div>`).join('');
}

document.getElementById('pqLevel').addEventListener('change', function(){
    document.getElementById('pqUniversityWrap').style.display = this.value === 'University' ? 'block' : 'none';
});

document.getElementById('pqGoBtn').addEventListener('click', () => {
    const subject = document.getElementById('pqSubject').value.trim();
    const level = document.getElementById('pqLevel').value;
    const year = document.getElementById('pqYear').value;
    const topic = document.getElementById('pqTopic').value.trim();
    const university = level === 'University' ? document.getElementById('pqUniversity').value : '';
    const exam = document.getElementById('pqExam').value;
    const errBox = document.getElementById('pqError');
    errBox.style.display = 'none';

    if (subject === '') { errBox.textContent = 'Type or pick a subject first.'; errBox.style.display = 'block'; return; }

    const titleBits = [subject];
    if (topic) titleBits.push(topic);
    if (year) titleBits.push(year);
    if (university) titleBits.push(university);
    document.getElementById('pqResultTitle').innerText = titleBits.join(' — ');

    const hasFilters = topic || year || university;

    // No filters at all AND it's in our local bank → instant, no AI call needed.
    if (!hasFilters) {
        const local = findLocal(subject);
        if (local) {
            renderList(local);
            document.getElementById('pqSetup').style.display = 'none';
            document.getElementById('pqResults').style.display = 'block';
            return;
        }
    }

    document.getElementById('pqLoading').style.display = 'block';
    document.getElementById('pqGoBtn').disabled = true;

    const fd = new FormData();
    fd.append('subject', subject);
    fd.append('topic', topic);
    fd.append('year', year);
    fd.append('level', level);
    fd.append('university', university);
    fd.append('exam', exam);

    fetch('past_questions_generate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('pqLoading').style.display = 'none';
            document.getElementById('pqGoBtn').disabled = false;
            if (!d.ok) {
                // Only fall back to the generic static bank if the person didn't
                // ask for a specific topic/year/school — otherwise a fallback
                // would silently show the same generic content every time,
                // which is the exact bug this is meant to fix. Show a clear
                // error instead so it's obvious the AI call needs a retry.
                if (!hasFilters) {
                    const local = findLocal(subject);
                    if (local) {
                        renderList(local);
                        document.getElementById('pqSetup').style.display = 'none';
                        document.getElementById('pqResults').style.display = 'block';
                        return;
                    }
                }
                errBox.textContent = d.text || 'Could not fetch past questions — try again.';
                errBox.style.display = 'block';
                return;
            }
            renderList(d.questions);
            document.getElementById('pqSetup').style.display = 'none';
            document.getElementById('pqResults').style.display = 'block';
        })
        .catch(() => {
            document.getElementById('pqLoading').style.display = 'none';
            document.getElementById('pqGoBtn').disabled = false;
            errBox.textContent = 'Network error — try again.';
            errBox.style.display = 'block';
        });
});

document.getElementById('pqChangeBtn')?.addEventListener('click', () => {
    document.getElementById('pqResults').style.display = 'none';
    document.getElementById('pqSetup').style.display = 'block';
});
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
