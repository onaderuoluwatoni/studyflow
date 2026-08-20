<?php
session_start();
include 'config/db.php';

$error = "";
$notice = "";

if (!isset($_SESSION['pending_unlock_user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['pending_unlock_user_id'];

$stmt = $conn->prepare("SELECT id, name, email, lock_code, lock_expires FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    unset($_SESSION['pending_unlock_user_id']);
    header("Location: login.php");
    exit();
}

if (isset($_POST['unlock'])) {
    $enteredCode = trim($_POST['code'] ?? '');

    if (!$user['lock_code'] || $enteredCode !== $user['lock_code']) {
        $error = "That code is incorrect. Please check your email and try again.";
    } elseif (!$user['lock_expires'] || strtotime($user['lock_expires']) < time()) {
        $error = "That code has expired. Please try logging in again to get a new one.";
    } else {
        $upd = $conn->prepare("UPDATE users SET failed_attempts = 0, lock_code = NULL, lock_expires = NULL WHERE id = ?");
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();

        unset($_SESSION['pending_unlock_user_id']);
        $notice = "Account unlocked. You can sign in now.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unlock your account — StudyFlow</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=2" rel="stylesheet">
</head>
<body>
<script>
(function(){ var t = localStorage.getItem('sf-theme') || 'dark'; if (t === 'dark') document.body.classList.add('sf-theme-deep'); })();
</script>

<div class="sf-auth-page">
    <div class="sf-brand">
        <img src="assets/img/logo.svg" alt="StudyFlow logo">
        <span class="sf-brand-text">Study<span style="color:var(--gold-light)">Flow</span></span>
    </div>

    <h1>Account locked.</h1>
    <p class="sf-auth-sub">Too many failed sign-in attempts. We emailed <strong><?php echo htmlspecialchars($user['email']); ?></strong> a code to unlock it.</p>

    <div class="sf-auth-card">
        <h2>Unlock your account</h2>

        <?php if ($error): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($notice): ?>
            <div class="sf-alert" style="background:rgba(31,111,92,0.12);color:#1f6f5c;border:1px solid rgba(31,111,92,0.3);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                <?php echo htmlspecialchars($notice); ?>
            </div>
            <a href="login.php" class="btn-sf-gold w-100 mt-2" style="display:block;text-align:center;text-decoration:none;">Back to sign in</a>
        <?php else: ?>
            <form method="POST">
                <div class="sf-field">
                    <label for="code">6-digit code</label>
                    <input type="text" id="code" name="code" placeholder="000000" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" style="letter-spacing:6px;font-size:22px;text-align:center;">
                </div>
                <button type="submit" name="unlock" class="btn-sf-gold w-100 mt-2">Unlock account</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
