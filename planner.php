<?php
session_start();
include 'config/db.php';
$active = 'planner';

$loggedIn = isset($_SESSION['user_id']);

if ($loggedIn) {
    $user_id = (int) $_SESSION['user_id'];

    if (isset($_POST['add_entry'])) {
        $day = $_POST['day'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $label = trim($_POST['label']);
        if ($label !== '') {
            $stmt = $conn->prepare("INSERT INTO timetable_entries (user_id, day_of_week, start_time, end_time, label) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('issss', $user_id, $day, $start, $end, $label);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: planner.php");
        exit();
    }

    if (isset($_GET['delete_entry'])) {
        $id = (int) $_GET['delete_entry'];
        $stmt = $conn->prepare("DELETE FROM timetable_entries WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: planner.php");
        exit();
    }
}

$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$timetable = array_fill_keys($days, []);

if ($loggedIn) {
    $stmt = $conn->prepare("SELECT * FROM timetable_entries WHERE user_id = ? ORDER BY start_time ASC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $timetable[$row['day_of_week']][] = $row;
    }
    $stmt->close();

    $stmt2 = $conn->prepare("
        SELECT tasks.*, subjects.subject_name
        FROM tasks
        LEFT JOIN subjects ON tasks.subject_id = subjects.id
        WHERE tasks.user_id = ?
        ORDER BY tasks.status ASC, tasks.due_date ASC
    ");
    $stmt2->bind_param('i', $user_id);
    $stmt2->execute();
    $tasks = $stmt2->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Study Planner</title>
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

<section class="sf-section" style="padding-bottom:90px;">
    <h2>Your schedule, assignments, and exam countdown</h2>

    <?php if (!$loggedIn): ?>
        <div class="sf-feature-card" style="max-width:520px;">
            <p class="mb-2">Sign in to build your own timetable and track assignments.</p>
            <a href="register.php" class="btn-sf-gold" style="margin:0;">Start Learning</a>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <h6 class="sf-section-eyebrow">Your weekly timetable</h6>
            <div class="row g-2 mb-3">
                <?php foreach ($days as $d): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="sf-feature-card">
                            <h6 class="mb-2"><?php echo $d; ?></h6>
                            <?php if (empty($timetable[$d])): ?>
                                <small class="text-muted">Nothing scheduled</small>
                            <?php else: foreach ($timetable[$d] as $e): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="mono"><?php echo htmlspecialchars($e['start_time']).'–'.htmlspecialchars($e['end_time']); ?></small>
                                    <span style="font-size:0.85rem;"><?php echo htmlspecialchars($e['label']); ?></span>
                                    <a href="planner.php?delete_entry=<?php echo $e['id']; ?>" style="font-size:0.75rem;color:var(--terracotta);">✕</a>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sf-feature-card mb-4">
                <h6 class="mb-2">Add to your timetable</h6>
                <form method="POST" class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small">Day</label>
                        <select name="day" class="form-select form-select-sm">
                            <?php foreach ($days as $d) echo "<option value=\"$d\">$d</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">Start</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">End</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small">Subject / activity</label>
                        <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. Physics revision" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="add_entry" class="btn-sf-gold w-100" style="margin:0;">Add</button>
                    </div>
                </form>
            </div>

            <h6 class="sf-section-eyebrow">Assignment tracker</h6>
            <div id="assignList">
                <?php if ($tasks->num_rows > 0): while ($t = $tasks->fetch_assoc()): ?>
                    <div class="sf-feature-card d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span><?php echo htmlspecialchars($t['task_title']); ?></span>
                            <br><small class="text-muted"><?php echo htmlspecialchars($t['subject_name'] ?? 'No subject'); ?> · due <?php echo htmlspecialchars($t['due_date']); ?></small>
                        </div>
                        <?php if ($t['status'] === 'completed'): ?>
                            <span class="sf-pill active">Submitted</span>
                        <?php else: ?>
                            <a href="tasks/complete_task.php?id=<?php echo $t['id']; ?>" class="sf-pill">Mark complete (+5 XP)</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-muted">No assignments yet. <a href="tasks/add_task.php">Add one</a>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <h6 class="sf-section-eyebrow">Exam countdown</h6>
            <div class="sf-feature-card text-center mb-4">
                <div class="sf-price" id="countdownNum">—</div>
                <small class="text-muted">days until your next exam</small>
                <input type="date" id="examDate" class="form-control mt-3">
            </div>

            <h6 class="sf-section-eyebrow">GPA / CGPA calculator</h6>
            <div class="sf-feature-card">
                <div id="gpaRows">
                    <div class="row g-2 mb-2 gpa-row">
                        <div class="col-6"><input class="form-control units" placeholder="Units" value="3"></div>
                        <div class="col-6"><input class="form-control grade" placeholder="Grade point (0-5)" value="4"></div>
                    </div>
                </div>
                <button class="sf-pill" id="addGpaRow" type="button">+ Add course</button>
                <button class="btn-sf-gold" style="margin-left:8px;" id="calcGpa" type="button">Calculate</button>
                <div class="mt-3">GPA: <strong id="gpaResult">—</strong></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const examInput = document.getElementById('examDate');
if (examInput) {
    examInput.addEventListener('change', function(){
        const d = new Date(this.value);
        const diff = Math.ceil((d - new Date())/(1000*60*60*24));
        document.getElementById('countdownNum').innerText = diff >= 0 ? diff : 0;
    });
}

const addRowBtn = document.getElementById('addGpaRow');
if (addRowBtn) {
    addRowBtn.addEventListener('click', function(){
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 gpa-row';
        div.innerHTML = `<div class="col-6"><input class="form-control units" placeholder="Units" value="3"></div>
                          <div class="col-6"><input class="form-control grade" placeholder="Grade point (0-5)" value="4"></div>`;
        document.getElementById('gpaRows').appendChild(div);
    });

    document.getElementById('calcGpa').addEventListener('click', function(){
        let totalUnits = 0, totalPoints = 0;
        document.querySelectorAll('.gpa-row').forEach(row => {
            const u = parseFloat(row.querySelector('.units').value) || 0;
            const g = parseFloat(row.querySelector('.grade').value) || 0;
            totalUnits += u; totalPoints += u*g;
        });
        document.getElementById('gpaResult').innerText = totalUnits ? (totalPoints/totalUnits).toFixed(2) : '—';
    });
}
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
