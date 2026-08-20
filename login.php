<?php
session_start();
include 'config/db.php';
include 'includes/streak.php';
include 'includes/mailer.php';

$error = "";

if (isset($_POST['login'])) {

    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        // Account is currently locked from too many failed attempts —
        // don't even check the password, send them to unlock first.
        if (!empty($user['lock_code']) && !empty($user['lock_expires']) && strtotime($user['lock_expires']) > time()) {
            $_SESSION['pending_unlock_user_id'] = $user['id'];
            header("Location: unlock.php");
            exit();
        }

        if (password_verify($password, $user['password'])) {

            if ((int) ($user['email_verified'] ?? 1) === 0) {
                // Account exists but email was never verified — send them to verify instead
                $_SESSION['pending_verification_user_id'] = $user['id'];
                $_SESSION['pending_verification_timezone'] = trim($_POST['timezone'] ?? '') ?: 'UTC';
                header("Location: verify.php");
                exit();
            }

            // Correct password — reset the failed-attempt counter.
            $uid0 = (int) $user['id'];
            $resetStmt = $conn->prepare("UPDATE users SET failed_attempts = 0 WHERE id = ?");
            $resetStmt->bind_param('i', $uid0);
            $resetStmt->execute();
            $resetStmt->close();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];

            // Keep the stored timezone in sync with whatever the browser
            // reports, then update the streak using that local day.
            $timezone = trim($_POST['timezone'] ?? '') ?: 'UTC';
            $uid = (int) $user['id'];
            $upd = $conn->prepare("UPDATE users SET timezone = ? WHERE id = ?");
            $upd->bind_param('si', $timezone, $uid);
            $upd->execute();
            $upd->close();
            $showDailyReward = sfUpdateStreakOnLogin($conn, $user['id'], $timezone);
            if ($showDailyReward) {
                $_SESSION['show_daily_reward'] = true;
                $_SESSION['daily_reward_claimable'] = true;
            }

            header("Location: index.php");
            exit();

        } else {
            // Wrong password — count the attempt. At 7, lock the account
            // and email a code so only the real owner can unlock it.
            $uid2 = (int) $user['id'];
            $attempts = (int) $user['failed_attempts'] + 1;

            if ($attempts >= 7) {
                $lockCode = sfGenerateCode();
                $lockExpires = date('Y-m-d H:i:s', time() + 15 * 60);
                $lockStmt = $conn->prepare("UPDATE users SET failed_attempts = ?, lock_code = ?, lock_expires = ? WHERE id = ?");
                $lockStmt->bind_param('issi', $attempts, $lockCode, $lockExpires, $uid2);
                $lockStmt->execute();
                $lockStmt->close();

                $mailError = null;
                sfSendMail($user['email'], $user['name'], "Your StudyFlow account was locked", "
                    <div style='font-family:Arial,sans-serif;max-width:420px;margin:0 auto;padding:24px;'>
                        <h2 style='color:#0b1526;'>Too many failed sign-in attempts</h2>
                        <p>Hi " . htmlspecialchars($user['name']) . ",</p>
                        <p>Your account was temporarily locked after 7 failed password attempts. Use this code to unlock it:</p>
                        <div style='font-size:32px;font-weight:700;letter-spacing:6px;background:#f4efe6;color:#0b1526;padding:16px;text-align:center;border-radius:8px;margin:16px 0;'>{$lockCode}</div>
                        <p style='color:#666;font-size:14px;'>This code expires in 15 minutes. If this wasn't you, consider resetting your password.</p>
                    </div>
                ", $mailError);

                $_SESSION['pending_unlock_user_id'] = $uid2;
                header("Location: unlock.php");
                exit();
            } else {
                $attStmt = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                $attStmt->bind_param('ii', $attempts, $uid2);
                $attStmt->execute();
                $attStmt->close();
            }

            $error = "Wrong password. Please try again.";
        }

    } else {
        $error = "No account found with that username or email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — StudyFlow</title>
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

<div class="sf-auth-page">
    <div class="sf-brand">
        <img src="assets/img/logo.svg" alt="StudyFlow logo">
        <span class="sf-brand-text">Study<span style="color:var(--gold-light)">Flow</span></span>
    </div>

    <h1>Welcome back to your study desk.</h1>
    <p class="sf-auth-sub">Pick up your subjects, tasks, and streaks right where you left off. Consistency compounds — one focused session at a time.</p>

    <div class="sf-auth-card">
        <h2>Sign in</h2>
        <p class="sub">Enter your details to access your dashboard.</p>

        <?php if ($error): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="sf-field">
                <label for="identifier">Username or email</label>
                <input type="text" id="identifier" name="identifier" placeholder="username or you@example.com" required>
            </div>
            <div class="sf-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
                <small style="display:block;margin-top:6px;text-align:right;">
                    <a href="forgot-password.php" style="color:var(--gold-light,#c9a15a);">Forgot password?</a>
                </small>
            </div>
            <input type="hidden" name="timezone" id="timezoneField">

            <button type="submit" name="login" class="btn-sf-gold w-100 mt-2">Sign in</button>
        </form>
        <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
            try {
                document.getElementById('timezoneField').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
            } catch (e) { /* falls back to UTC server-side */ }
        });
        </script>

        <div class="sf-auth-switch">
            New to StudyFlow? <a href="register.php">Create an account</a>
        </div>
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
</script>
</body>
</html>
