<?php
session_start();
include 'config/db.php';
include 'includes/streak.php';
include 'includes/mailer.php';
include 'includes/username_rules.php';

$error = "";

if (isset($_POST['register'])) {

    $name = trim($_POST['name']);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $timezone = trim($_POST['timezone'] ?? '') ?: 'UTC';

    if (!sfUsernameFormatValid($username)) {
        $error = "Username must be 3–20 characters (letters/numbers only), include at least one \".\" or \"_\", and can't start or end with a period.";
    } elseif (sfUsernameUnavailable($conn, $username)) {
        $error = "That username is unavailable — taken or too similar to an existing username.";
    } else {

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result();
    $stmt->close();

    if ($existing->num_rows > 0) {
        $error = "That email is already registered.";
    } else {
        $code = sfGenerateCode();
        $expires = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes

        $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, timezone, email_verified, verification_code, verification_expires) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->bind_param('sssssss', $name, $username, $email, $password, $timezone, $code, $expires);

        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            $stmt->close();

            $mailError = null;
            if (!sfSendVerificationEmail($email, $name, $code, $mailError)) {
                // Roll back so a broken mail setup doesn't leave dead unverified accounts behind
                $del = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del->bind_param('i', $newUserId);
                $del->execute();
                $del->close();
                $error = "Couldn't send the verification email. Please try again in a moment.";
                if ($mailError) {
                    $error .= " (" . $mailError . ")";
                }
            } else {
                // Don't log them in yet — they must verify first
                $_SESSION['pending_verification_user_id'] = $newUserId;
                $_SESSION['pending_verification_timezone'] = $timezone;
                header("Location: verify.php");
                exit();
            }
        } else {
            $error = "Something went wrong. Please try again.";
            $stmt->close();
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account — StudyFlow</title>
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

    <h1>Start your study streak today.</h1>
    <p class="sf-auth-sub">Track subjects, plan tasks, and build focus with the Pomodoro timer — all in one clean dashboard.</p>

    <div class="sf-auth-card">
        <h2>Create your account</h2>
        <p class="sub">It only takes a minute to get started.</p>

        <?php if ($error): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="sf-field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" placeholder="Your name" required>
            </div>
            <div class="sf-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="e.g. toni.studies" required minlength="3" maxlength="20" pattern="(?=.{3,20}$)(?!.*\.\.)(?!\.)[a-zA-Z0-9_.]*[_.][a-zA-Z0-9_.]*">
                <small style="display:block;margin-top:4px;color:var(--text-muted);">Must include at least one "." or "_" — e.g. toni.studies or toni_studies</small>
                <small id="usernameStatus" style="display:block;margin-top:4px;"></small>
            </div>
            <div class="sf-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="sf-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <input type="hidden" name="timezone" id="timezoneField">

            <button type="submit" name="register" class="btn-sf-gold w-100 mt-2" id="registerSubmitBtn">Create account</button>
        </form>
        <script>
        let usernameAvailable = false;
        let usernameCheckTimer = null;
        const usernameInput = document.getElementById('username');
        const usernameStatus = document.getElementById('usernameStatus');

        usernameInput.addEventListener('input', function () {
            const val = this.value.trim();
            usernameAvailable = false;
            clearTimeout(usernameCheckTimer);
            if (val.length < 3) { usernameStatus.textContent = ''; return; }
            usernameStatus.textContent = 'Checking...';
            usernameStatus.style.color = 'var(--text-muted, #888)';
            usernameCheckTimer = setTimeout(() => {
                fetch('check_username.php?username=' + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(d => {
                        if (d.available) {
                            usernameAvailable = true;
                            usernameStatus.textContent = '✓ Username available';
                            usernameStatus.style.color = 'var(--teal, #1f6f5c)';
                        } else {
                            usernameAvailable = false;
                            usernameStatus.textContent = '✕ ' + (d.reason || 'Username taken.');
                            usernameStatus.style.color = 'var(--terracotta, #b0432b)';
                        }
                    });
            }, 400);
        });

        document.getElementById('registerForm').addEventListener('submit', function (e) {
            try {
                document.getElementById('timezoneField').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
            } catch (e) { /* falls back to UTC server-side */ }
            if (!usernameAvailable) {
                e.preventDefault();
                usernameStatus.textContent = '✕ Please choose an available username first.';
                usernameStatus.style.color = 'var(--terracotta, #b0432b)';
                usernameInput.focus();
            }
        });
        </script>

        <div class="sf-auth-switch">
            Already have an account? <a href="login.php">Sign in</a>
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
