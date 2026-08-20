<?php
session_start();
include 'config/db.php';
include 'includes/friends.php';
include 'includes/coach.php';
include 'includes/exam_goals.php';
include 'includes/username_rules.php';
$active = 'profile';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$viewId = isset($_GET['id']) ? (int) $_GET['id'] : $user_id;
$isSelf = ($viewId === $user_id);

$settingsMessage = "";
$settingsError = "";

// Curated avatar set — simple, no file upload needed. Stored as a short key.
$avatarOptions = [
    'fox' => '🦊', 'owl' => '🦉', 'cat' => '🐱', 'panda' => '🐼',
    'lion' => '🦁', 'koala' => '🐨', 'penguin' => '🐧', 'wolf' => '🐺',
    'rocket' => '🚀', 'star' => '⭐', 'book' => '📚', 'bulb' => '💡',
];

if ($isSelf && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['avatar'])) {
        $avatar = trim($_POST['avatar'] ?? '');
        if (array_key_exists($avatar, $avatarOptions)) {
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->bind_param('si', $avatar, $user_id);
            $stmt->execute();
            $stmt->close();
            $settingsMessage = "Avatar updated.";
        }
    }

    if (isset($_POST['update_name'])) {
        $newName = trim($_POST['name'] ?? '');
        if ($newName === '') {
            $settingsError = "Name can't be empty.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $newName, $user_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['name'] = $newName;
            $settingsMessage = "Name updated.";
        }
    }

    if (isset($_POST['update_username'])) {
        $newUsername = trim($_POST['username'] ?? '');

        if (!sfUsernameFormatValid($newUsername)) {
            $settingsError = "Username must be 3–20 characters, include at least one \".\" or \"_\", and can't start or end with a period.";
        } elseif (sfUsernameUnavailable($conn, $newUsername, $user_id)) {
            $settingsError = "That username is unavailable — taken or too similar to an existing username.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param('si', $newUsername, $user_id);
            $stmt->execute();
            $stmt->close();
            $settingsMessage = "Username updated.";
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($currentPassword, $row['password'])) {
            $settingsError = "Current password is incorrect.";
        } elseif (strlen($newPassword) < 6) {
            $settingsError = "New password must be at least 6 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $settingsError = "New passwords don't match.";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed, $user_id);
            $stmt->execute();
            $stmt->close();
            $settingsMessage = "Password updated.";
        }
    }

    if (isset($_POST['delete_account'])) {
        $confirmPassword = $_POST['delete_password'] ?? '';
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($confirmPassword, $row['password'])) {
            $settingsError = "Password is incorrect — account not deleted.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            session_destroy();
            header("Location: home.php?deleted=1");
            exit();
        }
    }
}

// Only allow viewing another user's profile if they're an accepted friend.
if (!$isSelf) {
    $rel = sfFriendStatus($conn, $user_id, $viewId);
    if (!$rel || $rel['status'] !== 'accepted') {
        header("Location: friends.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT name, username, avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $viewId);
$stmt->execute();
$viewUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$viewUser) {
    header("Location: friends.php");
    exit();
}

$exams = include 'includes/exam_goals.php';
$viewStats = sfGetUserStats($conn, $viewId);
$viewBadges = sfGetBadges($conn, $viewId);

if (!$isSelf) {
    $myStats = sfGetUserStats($conn, $user_id);
    $myBadges = sfGetBadges($conn, $user_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — <?php echo htmlspecialchars($viewUser['name']); ?></title>
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
(function(){
  var t = localStorage.getItem('sf-theme') || 'dark';
  if (t === 'dark') document.body.classList.add('sf-theme-deep');
})();
</script>
<?php include 'includes/navbar.php'; ?>

<section class="sf-section">

    <div class="sf-profile-avatar-wrap">
        <?php if ($isSelf && !empty($viewUser['avatar']) && isset($avatarOptions[$viewUser['avatar']])): ?>
            <div class="sf-profile-avatar has-emoji"><?php echo $avatarOptions[$viewUser['avatar']]; ?></div>
        <?php elseif (!$isSelf && !empty($viewUser['avatar'])): ?>
            <div class="sf-profile-avatar has-emoji"><?php echo htmlspecialchars($viewUser['avatar']); ?></div>
        <?php else: ?>
            <div class="sf-profile-avatar default">
                <svg viewBox="0 0 24 24" width="44" height="44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="4" fill="currentColor"/>
                    <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8" fill="currentColor"/>
                </svg>
            </div>
        <?php endif; ?>
    </div>

    <h2><?php echo htmlspecialchars($viewUser['name']); ?><?php echo $isSelf ? ' (you)' : ''; ?></h2>
    <?php if (!empty($viewUser['username'])): ?>
        <p class="text-muted" style="margin-top:-8px;">@<?php echo htmlspecialchars($viewUser['username']); ?></p>
    <?php endif; ?>

    <?php if ($isSelf): ?>
        <!-- SOLO VIEW -->
        <div class="row g-3 mt-2">
            <div class="col-md-3 col-6">
                <div class="sf-stat-card gold"><div class="sf-stat-icon">⚡</div><h5 class="mb-0"><?php echo $viewStats['xp']; ?></h5><p class="text-muted small mb-0">XP</p></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="sf-stat-card teal"><div class="sf-stat-icon">🔥</div><h5 class="mb-0"><?php echo $viewStats['streak']; ?></h5><p class="text-muted small mb-0">Day streak</p></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="sf-stat-card terracotta"><div class="sf-stat-icon">🧠</div><h5 class="mb-0"><?php echo $viewStats['quiz_avg']; ?>%</h5><p class="text-muted small mb-0">Quiz average (<?php echo $viewStats['quiz_count']; ?> taken)</p></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="sf-stat-card gold"><div class="sf-stat-icon">🪙</div><h5 class="mb-0"><?php echo $viewStats['coins']; ?></h5><p class="text-muted small mb-0">Coins</p></div>
            </div>
        </div>
        <p class="mt-3 text-muted">Reading for <strong><?php echo htmlspecialchars($exams[$viewStats['exam_goal']] ?? 'General studies'); ?></strong></p>

        <h6 class="sf-section-eyebrow mt-4 mb-2">Badges</h6>
        <?php if (empty($viewBadges)): ?>
            <p class="text-muted">No badges yet — keep studying to earn your first one.</p>
        <?php else: ?>
            <div class="d-flex gap-3 flex-wrap">
                <?php foreach ($viewBadges as $b): ?>
                    <div class="sf-feature-card text-center" style="width:120px;">
                        <div style="font-size:1.8rem;"><?php echo $b['icon']; ?></div>
                        <small><?php echo htmlspecialchars($b['label']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h6 class="sf-section-eyebrow mt-4 mb-2">Account settings</h6>

        <?php if ($settingsMessage): ?>
            <div class="sf-alert sf-alert-success"><?php echo htmlspecialchars($settingsMessage); ?></div>
        <?php endif; ?>
        <?php if ($settingsError): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($settingsError); ?></div>
        <?php endif; ?>

        <div class="sf-feature-card mb-3">
            <h6 class="mb-3">Choose your avatar</h6>
            <form method="POST">
                <div class="sf-avatar-grid">
                    <?php foreach ($avatarOptions as $key => $emoji): ?>
                        <button type="submit" name="avatar" value="<?php echo $key; ?>"
                            class="sf-avatar-option <?php echo ($viewUser['avatar'] ?? '') === $key ? 'selected' : ''; ?>">
                            <?php echo $emoji; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <div class="sf-feature-card mb-3">
            <h6 class="mb-1">App color</h6>
            <p class="text-muted small mb-3">Choose the accent color for the whole app — buttons, highlights, glowing panels.</p>
            <div class="d-flex gap-2 flex-wrap" id="accentSwatches">
                <button type="button" class="sf-accent-swatch" data-accent="gold" style="background:#c9a227;" title="Gold (default)"></button>
                <button type="button" class="sf-accent-swatch" data-accent="pink" style="background:#d6558c;" title="Pink"></button>
                <button type="button" class="sf-accent-swatch" data-accent="green" style="background:#2f9e5c;" title="Green"></button>
                <button type="button" class="sf-accent-swatch" data-accent="blue" style="background:#3b8ce0;" title="Blue"></button>
                <button type="button" class="sf-accent-swatch" data-accent="purple" style="background:#8b5ce0;" title="Purple"></button>
                <button type="button" class="sf-accent-swatch" data-accent="black" style="background:#4a4a4a;" title="Black & white"></button>
            </div>
        </div>

        <script>
        (function(){
            var current = localStorage.getItem('sf-accent') || 'gold';
            var swatches = document.querySelectorAll('#accentSwatches .sf-accent-swatch');
            swatches.forEach(function(s){
                if (s.dataset.accent === current) s.classList.add('selected');
                s.addEventListener('click', function(){
                    swatches.forEach(function(x){ x.classList.remove('selected'); });
                    s.classList.add('selected');
                    localStorage.setItem('sf-accent', s.dataset.accent);
                    if (s.dataset.accent === 'gold') document.body.removeAttribute('data-accent');
                    else document.body.setAttribute('data-accent', s.dataset.accent);
                });
            });
        })();
        </script>
            <form method="POST" class="d-flex gap-2 flex-wrap">
                <input type="text" name="name" class="form-control" style="max-width:280px;" value="<?php echo htmlspecialchars($viewUser['name']); ?>" required>
                <button type="submit" name="update_name" class="btn-sf-gold" style="margin:0;">Save</button>
            </form>
        </div>

        <div class="sf-feature-card mb-3">
            <h6 class="mb-3">Edit username</h6>
            <form method="POST" class="d-flex gap-2 flex-wrap">
                <input type="text" name="username" class="form-control" style="max-width:280px;" value="<?php echo htmlspecialchars($viewUser['username'] ?? ''); ?>" placeholder="e.g. toni.studies" required>
                <button type="submit" name="update_username" class="btn-sf-gold" style="margin:0;">Save</button>
            </form>
            <small class="text-muted d-block mt-2">Must include at least one "." or "_" — e.g. toni.studies or toni_studies</small>
        </div>

        <div class="sf-feature-card mb-3">
            <h6 class="mb-3">Change password</h6>
            <form method="POST">
                <div class="sf-field">
                    <label>Current password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="sf-field">
                    <label>New password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="sf-field">
                    <label>Confirm new password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" name="change_password" class="btn-sf-gold">Update password</button>
            </form>
        </div>

        <div class="sf-feature-card mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-1">Log out</h6>
                <p class="text-muted small mb-0">Sign out of StudyFlow on this device.</p>
            </div>
            <a href="logout.php" class="btn-sf-outline" style="border:1px solid var(--border);border-radius:8px;padding:10px 20px;text-decoration:none;">Log out</a>
        </div>

        <div class="sf-feature-card mb-3" style="border-color:rgba(176,67,43,0.35);">
            <h6 class="mb-1" style="color:var(--terracotta);">Delete account</h6>
            <p class="text-muted small">This permanently deletes your account and all your data — tasks, flashcards, quiz history, friends, everything. This can't be undone.</p>
            <button type="button" class="sf-pill" style="border-color:var(--terracotta);color:var(--terracotta);" onclick="document.getElementById('sfDeleteConfirm').style.display='block'; this.style.display='none';">Delete my account</button>
            <form method="POST" id="sfDeleteConfirm" style="display:none;margin-top:12px;">
                <div class="sf-field">
                    <label>Type your password to confirm</label>
                    <input type="password" name="delete_password" required>
                </div>
                <button type="submit" name="delete_account" class="sf-pill" style="border-color:var(--terracotta);background:var(--terracotta);color:#fff;" onclick="return confirm('This is permanent. Delete your account?');">Yes, permanently delete my account</button>
            </form>
        </div>

    <?php else: ?>
        <!-- COMPARISON VIEW -->
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <div class="sf-feature-card">
                    <h6 class="mb-3">You</h6>
                    <p class="mb-1">⚡ <?php echo $myStats['xp']; ?> XP</p>
                    <p class="mb-1">🔥 <?php echo $myStats['streak']; ?>-day streak (longest <?php echo $myStats['longest_streak']; ?>)</p>
                    <p class="mb-1">🧠 <?php echo $myStats['quiz_avg']; ?>% quiz average (<?php echo $myStats['quiz_count']; ?> taken)</p>
                    <p class="mb-0">🪙 <?php echo $myStats['coins']; ?> coins</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="sf-feature-card">
                    <h6 class="mb-3"><?php echo htmlspecialchars($viewUser['name']); ?></h6>
                    <p class="mb-1">⚡ <?php echo $viewStats['xp']; ?> XP</p>
                    <p class="mb-1">🔥 <?php echo $viewStats['streak']; ?>-day streak (longest <?php echo $viewStats['longest_streak']; ?>)</p>
                    <p class="mb-1">🧠 <?php echo $viewStats['quiz_avg']; ?>% quiz average (<?php echo $viewStats['quiz_count']; ?> taken)</p>
                    <p class="mb-0">🪙 <?php echo $viewStats['coins']; ?> coins</p>
                </div>
            </div>
        </div>
        <a href="friends.php" class="btn-sf-outline mt-3 d-inline-block" style="border:1px solid var(--border);border-radius:8px;padding:10px 18px;">&larr; Back to friends</a>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
</script>
</body>
</html>
