<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$showDailyRewardPopup = !empty($_SESSION['show_daily_reward']);
unset($_SESSION['show_daily_reward']); // one-shot — only render this once

/* STATS */
$total = $conn->query("SELECT * FROM tasks WHERE user_id='$user_id'")->num_rows;
$completed = $conn->query("SELECT * FROM tasks WHERE user_id='$user_id' AND status='completed'")->num_rows;
$pending = $conn->query("SELECT * FROM tasks WHERE user_id='$user_id' AND status='pending'")->num_rows;

$streakQuery = $conn->query("SELECT * FROM user_streaks WHERE user_id='$user_id'");
$streakData = $streakQuery->fetch_assoc();
$streak = $streakData['streak_count'] ?? 0;

$xpRow = $conn->query("SELECT xp FROM users WHERE id='$user_id'")->fetch_assoc();
$myXp = (int) ($xpRow['xp'] ?? 0);
$xpPerLevel = 50;
$level = intdiv(max($myXp, 0), $xpPerLevel) + 1;
$xpIntoLevel = max($myXp, 0) % $xpPerLevel;
$xpPercent = (int) round(($xpIntoLevel / $xpPerLevel) * 100);

/* SMART DASHBOARD DATA */
$today = date("Y-m-d");

$dueToday = $conn->query("
    SELECT tasks.*, subjects.subject_name 
    FROM tasks 
    LEFT JOIN subjects ON tasks.subject_id = subjects.id 
    WHERE tasks.user_id='$user_id' 
    AND tasks.due_date='$today'
    AND tasks.status='pending'
");

$overdue = $conn->query("
    SELECT tasks.*, subjects.subject_name 
    FROM tasks 
    LEFT JOIN subjects ON tasks.subject_id = subjects.id 
    WHERE tasks.user_id='$user_id'
    AND tasks.due_date < '$today'
    AND tasks.status='pending'
");

$weekStart = date("Y-m-d", strtotime("monday this week"));
$weekEnd = date("Y-m-d", strtotime("sunday this week"));

$weekTasks = $conn->query("
    SELECT * FROM tasks 
    WHERE user_id='$user_id'
    AND status = 'pending'
    AND (due_date IS NULL OR due_date BETWEEN '$weekStart' AND '$weekEnd')
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyFlow — Dashboard</title>

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

<div class="sf-watermark"></div>

<div class="d-flex sf-content-above">

    <!-- SIDEBAR -->
    <div class="sf-sidebar">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="sf-brand mb-0">
                <img src="assets/img/logo.svg" alt="StudyFlow logo">
                <span class="sf-brand-text">Study<span class="accent" style="color:var(--gold-light)">Flow</span></span>
            </div>
            <button type="button" id="sfSidebarToggle" class="sf-sidebar-hamburger" aria-label="Toggle menu">&#9776;</button>
        </div>

        <a href="index.php" class="sf-nav-link active">Dashboard</a>
        <a href="subjects/add_subject.php" class="sf-nav-link">Add Subject</a>
        <a href="tasks/add_task.php" class="sf-nav-link">Add Task</a>

        <div id="sfSidebarMore" class="sf-sidebar-more">
            <hr>
            <a href="past-questions.php" class="sf-nav-link">Past Questions</a>
            <a href="ai-tutor.php" class="sf-nav-link">AI Tutor</a>
            <a href="quiz.php" class="sf-nav-link">Quiz</a>
            <a href="flashcards.php" class="sf-nav-link">Flashcards</a>
            <a href="planner.php" class="sf-nav-link">Study Planner</a>
            <a href="resources.php" class="sf-nav-link">Resources</a>
            <a href="community.php" class="sf-nav-link">Community</a>
            <a href="friends.php" class="sf-nav-link">Friends</a>
        </div>

        <hr>

        <?php
        $sidebarAvatarOptions = ['fox'=>'🦊','owl'=>'🦉','cat'=>'🐱','panda'=>'🐼','lion'=>'🦁','koala'=>'🐨','penguin'=>'🐧','wolf'=>'🐺','rocket'=>'🚀','star'=>'⭐','book'=>'📚','bulb'=>'💡'];
        $avStmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
        $avStmt->bind_param('i', $user_id);
        $avStmt->execute();
        $avRow = $avStmt->get_result()->fetch_assoc();
        $avStmt->close();
        $sidebarAvatar = $avRow['avatar'] ?? null;
        ?>
        <a href="profile.php" class="sf-nav-link d-flex align-items-center gap-2">
            <?php if (!empty($sidebarAvatar) && isset($sidebarAvatarOptions[$sidebarAvatar])): ?>
                <span class="sf-nav-avatar has-emoji" style="width:26px;height:26px;font-size:14px;"><?php echo $sidebarAvatarOptions[$sidebarAvatar]; ?></span>
            <?php else: ?>
                <span class="sf-nav-avatar default" style="width:26px;height:26px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                </span>
            <?php endif; ?>
            My Profile
            <span class="sf-nav-xp-badge">⚡ <?php echo (int) $myXp; ?> XP</span>
        </a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="flex-grow-1 p-4">

        <div class="sf-dash-hero">
            <div class="sf-dash-hero-glow"></div>
            <div class="sf-dash-hero-content">
                <div>
                    <div class="sf-section-eyebrow" style="color:rgba(255,255,255,0.55);"><?php echo date('l, j F Y'); ?></div>
                    <h3 class="mb-1" style="color:#fff;">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                    <p class="mb-0" style="color:rgba(255,255,255,0.6);font-size:0.92rem;">
                        <?php if ($streak > 0): ?>
                            🔥 <?php echo $streak; ?>-day streak — keep it going.
                        <?php else: ?>
                            Ready to start today's streak?
                        <?php endif; ?>
                    </p>
                </div>
                <div class="sf-dash-hero-level">
                    <div class="sf-level-ring">
                        <svg viewBox="0 0 80 80" width="72" height="72">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="7"/>
                            <circle cx="40" cy="40" r="34" fill="none" stroke="url(#sfGoldGrad)" stroke-width="7"
                                stroke-linecap="round"
                                stroke-dasharray="<?php echo round(2 * 3.14159 * 34); ?>"
                                stroke-dashoffset="<?php echo round(2 * 3.14159 * 34 * (1 - $xpPercent / 100)); ?>"
                                transform="rotate(-90 40 40)"/>
                            <defs>
                                <linearGradient id="sfGoldGrad" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#e9c96a"/>
                                    <stop offset="100%" stop-color="#9a7418"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="sf-level-ring-label">
                            <div class="lvl">Lv <?php echo $level; ?></div>
                        </div>
                    </div>
                    <div class="sf-level-xp-text"><?php echo $xpIntoLevel; ?> / <?php echo $xpPerLevel; ?> XP to next level</div>
                </div>
            </div>
        </div>

        <!-- STATS -->
        <div class="row g-3 sf-stat-row">
            <div class="col-6 col-md-3">
                <div class="sf-stat-card">
                    <div class="sf-stat-icon">🗂️</div>
                    <div class="label">Total tasks</div>
                    <div class="value"><?php echo $total; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sf-stat-card teal">
                    <div class="sf-stat-icon">✅</div>
                    <div class="label">Completed</div>
                    <div class="value"><?php echo $completed; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sf-stat-card gold">
                    <div class="sf-stat-icon">⏳</div>
                    <div class="label">Pending</div>
                    <div class="value"><?php echo $pending; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="sf-stat-card gold">
                    <div class="sf-stat-icon">🔥</div>
                    <div class="label">Streak</div>
                    <div class="value"><?php echo $streak; ?></div>
                </div>
            </div>
        </div>

        <!-- DUE TODAY -->
        <div class="mt-5">
            <h4 class="sf-section-title">Tasks due today</h4>

            <div class="row g-3">
                <?php if ($dueToday->num_rows > 0) {
                    while ($row = $dueToday->fetch_assoc()) { ?>
                        <div class="col-md-4">
                            <div class="card p-3" style="border-left:4px solid var(--gold);">
                                <h6 class="mb-1"><?php echo htmlspecialchars($row['task_title']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($row['subject_name']); ?></small><br>
                                <small class="text-muted">Due today</small>
                            </div>
                        </div>
                <?php }} else {
                    echo "<p class='text-muted'>No tasks due today — you're clear.</p>";
                } ?>
            </div>
        </div>

        <!-- OVERDUE -->
        <div class="mt-5">
            <h4 class="sf-section-title">Overdue tasks</h4>

            <div class="row g-3">
                <?php if ($overdue->num_rows > 0) {
                    while ($row = $overdue->fetch_assoc()) { ?>
                        <div class="col-md-4">
                            <div class="card p-3" style="border-left:4px solid var(--terracotta);">
                                <h6 class="mb-1"><?php echo htmlspecialchars($row['task_title']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($row['subject_name']); ?></small><br>
                                <small style="color:var(--terracotta);">Was due <?php echo htmlspecialchars($row['due_date']); ?></small>
                            </div>
                        </div>
                <?php }} else {
                    echo "<p class='text-muted'>No overdue tasks. Nicely done.</p>";
                } ?>
            </div>
        </div>

        <!-- WEEK SUMMARY -->
        <div class="mt-5">
            <h4 class="sf-section-title">This week</h4>
            <div class="card p-3">
                <p class="mb-0">Total tasks this week: <b class="mono"><?php echo $weekTasks->num_rows; ?></b></p>
            </div>
        </div>

        <!-- SUBJECTS -->
        <div class="mt-5">
            <h4 class="sf-section-title">My subjects</h4>
            <div class="row g-3">
                <?php
                $subjects = $conn->query("SELECT * FROM subjects WHERE user_id='$user_id' ORDER BY id DESC");
                if ($subjects->num_rows > 0) {
                    while ($row = $subjects->fetch_assoc()) {
                ?>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <h6 class="mb-0"><?php echo htmlspecialchars($row['subject_name']); ?></h6>
                        </div>
                    </div>
                <?php }
                } else {
                    echo "<p class='text-muted'>No subjects yet. <a href='subjects/add_subject.php'>Add one</a>.</p>";
                } ?>
            </div>
        </div>

        <!-- TASKS -->
        <div class="mt-5">
            <h4 class="sf-section-title">My tasks</h4>
            <div class="row g-3">

                <?php
                $tasks = $conn->query("
                    SELECT tasks.*, subjects.subject_name 
                    FROM tasks 
                    LEFT JOIN subjects ON tasks.subject_id = subjects.id 
                    WHERE tasks.user_id='$user_id'
                    ORDER BY tasks.id DESC
                ");

                if ($tasks->num_rows > 0) {
                    while ($row = $tasks->fetch_assoc()) {
                ?>

                    <div class="col-md-4">
                        <div class="card p-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($row['task_title']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($row['subject_name']); ?></small><br>
                            <small class="text-muted"><?php echo htmlspecialchars($row['due_date']); ?></small><br>

                            <span class="badge <?php echo $row['status'] === 'completed' ? 'badge-completed' : 'badge-pending'; ?> mt-2">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </div>
                    </div>

                <?php }
                } else {
                    echo "<p class='text-muted'>No tasks yet. <a href='tasks/add_task.php'>Add one</a>.</p>";
                } ?>

            </div>
        </div>

    </div>
</div>

<?php if ($showDailyRewardPopup): ?>
<div id="dailyRewardOverlay" class="sf-reward-overlay">
    <canvas id="sfConfettiCanvas"></canvas>
    <div class="sf-reward-card">
        <div class="sf-reward-icon">🔥</div>
        <h3>Welcome back!</h3>
        <p>You kept your streak alive. Grab your daily bonus before it's gone.</p>
        <div class="sf-reward-xp">+2 XP</div>
        <div class="sf-reward-actions">
            <button id="sfRewardClaim" class="btn-sf-gold">Claim reward</button>
            <button id="sfRewardDecline" class="sf-reward-decline">Not now</button>
        </div>
        <small class="sf-reward-hint">Come back tomorrow for another one.</small>
    </div>
</div>

<style>
.sf-reward-overlay {
    position: fixed; inset: 0; z-index: 3000;
    display: flex; align-items: center; justify-content: center;
    background: rgba(6, 12, 24, 0.72);
    backdrop-filter: blur(4px);
    animation: sfFadeIn 0.25s ease;
}
@keyframes sfFadeIn { from { opacity: 0; } to { opacity: 1; } }
#sfConfettiCanvas { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.sf-reward-card {
    position: relative;
    width: min(360px, 88vw);
    padding: 32px 26px 26px;
    text-align: center;
    border-radius: 20px;
    background: linear-gradient(180deg, rgba(20,35,56,0.92), rgba(11,21,38,0.96));
    border: 1px solid var(--glass-border);
    backdrop-filter: var(--glass-blur);
    box-shadow: 0 24px 60px rgba(0,0,0,0.45), 0 0 0 1px rgba(233,201,106,0.08);
    animation: sfPopIn 0.35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes sfPopIn { from { opacity: 0; transform: scale(0.85) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.sf-reward-icon {
    font-size: 42px; line-height: 1; margin-bottom: 6px;
    filter: drop-shadow(0 0 14px rgba(233,201,106,0.5));
}
.sf-reward-card h3 { color: #fff; font-family: var(--font-serif, serif); font-size: 1.4rem; margin: 4px 0 6px; }
.sf-reward-card p { color: rgba(255,255,255,0.7); font-size: 0.92rem; margin-bottom: 16px; }
.sf-reward-xp {
    display: inline-block;
    font-family: var(--font-mono);
    font-weight: 700;
    font-size: 1.5rem;
    letter-spacing: 0.04em;
    color: var(--gold-light);
    background: rgba(233,201,106,0.1);
    border: 1px solid rgba(233,201,106,0.3);
    border-radius: 12px;
    padding: 8px 22px;
    margin-bottom: 20px;
}
.sf-reward-actions { display: flex; flex-direction: column; gap: 10px; }
.sf-reward-decline {
    background: transparent; border: none; color: rgba(255,255,255,0.55);
    font-size: 0.88rem; padding: 6px; cursor: pointer;
}
.sf-reward-decline:hover { color: rgba(255,255,255,0.85); }
.sf-reward-hint { display: block; margin-top: 14px; color: rgba(255,255,255,0.4); font-size: 0.78rem; }
.sf-reward-card.sf-reward-claimed .sf-reward-actions,
.sf-reward-card.sf-reward-claimed .sf-reward-hint { display: none; }
.sf-reward-claimed-msg { display: none; color: var(--gold-light); font-weight: 600; margin-top: 4px; }
.sf-reward-card.sf-reward-claimed .sf-reward-claimed-msg { display: block; }
</style>

<script>
(function() {
    const overlay = document.getElementById('dailyRewardOverlay');
    const card = overlay.querySelector('.sf-reward-card');
    const claimBtn = document.getElementById('sfRewardClaim');
    const declineBtn = document.getElementById('sfRewardDecline');

    function closeOverlay() {
        overlay.style.animation = 'sfFadeIn 0.2s ease reverse';
        setTimeout(() => overlay.remove(), 180);
    }

    function fireConfetti() {
        const canvas = document.getElementById('sfConfettiCanvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const colors = ['#e9c96a', '#c9a227', '#9a7418', '#ffffff'];
        const pieces = Array.from({ length: 90 }, () => ({
            x: canvas.width / 2 + (Math.random() - 0.5) * 60,
            y: canvas.height / 2,
            vx: (Math.random() - 0.5) * 12,
            vy: Math.random() * -10 - 4,
            size: Math.random() * 6 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            rot: Math.random() * 360,
            vr: (Math.random() - 0.5) * 12,
        }));
        let frame = 0;
        function tick() {
            frame++;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            pieces.forEach(p => {
                p.vy += 0.35;
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vr;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                ctx.restore();
            });
            if (frame < 130) requestAnimationFrame(tick);
            else ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        tick();
    }

    claimBtn.addEventListener('click', () => {
        claimBtn.disabled = true;
        fireConfetti();
        fetch('claim_daily_reward.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=claim'
        })
        .then(r => r.json())
        .then(d => {
            card.classList.add('sf-reward-claimed');
            const msg = document.createElement('div');
            msg.className = 'sf-reward-claimed-msg';
            msg.innerText = d.ok ? 'Claimed! See you tomorrow 🎉' : 'Something went wrong.';
            card.appendChild(msg);
            setTimeout(closeOverlay, 1600);
        })
        .catch(() => {
            claimBtn.disabled = false;
        });
    });

    declineBtn.addEventListener('click', () => {
        fetch('claim_daily_reward.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=decline'
        }).catch(() => {});
        closeOverlay();
    });
})();
</script>
<?php endif; ?>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
(function(){
    var toggle = document.getElementById('sfSidebarToggle');
    var more = document.getElementById('sfSidebarMore');
    if (!toggle || !more) return;
    var open = sessionStorage.getItem('sf-sidebar-open') === '1';
    more.classList.toggle('open', open);
    toggle.addEventListener('click', function(){
        open = !open;
        more.classList.toggle('open', open);
        sessionStorage.setItem('sf-sidebar-open', open ? '1' : '0');
    });
})();
</script>
</body>
</html>
