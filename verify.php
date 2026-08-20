<?php
session_start();
include 'config/db.php';
include 'includes/streak.php';
include 'includes/mailer.php';

$error = "";
$notice = "";

if (!isset($_SESSION['pending_verification_user_id'])) {
    header("Location: register.php");
    exit();
}

$userId = (int) $_SESSION['pending_verification_user_id'];

$stmt = $conn->prepare("SELECT id, name, email, timezone, email_verified, verification_code, verification_expires FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    unset($_SESSION['pending_verification_user_id']);
    header("Location: register.php");
    exit();
}

if ((int) $user['email_verified'] === 1) {
    // Already verified somehow (e.g. double submit) — just log them in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    unset($_SESSION['pending_verification_user_id']);
    header("Location: index.php");
    exit();
}

// Handle "resend code"
if (isset($_POST['resend'])) {
    $code = sfGenerateCode();
    $expires = date('Y-m-d H:i:s', time() + 15 * 60);
    $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $upd->bind_param('ssi', $code, $expires, $userId);
    $upd->execute();
    $upd->close();

    $mailError = null;
    if (sfSendVerificationEmail($user['email'], $user['name'], $code, $mailError)) {
        $notice = "A new code has been sent to " . htmlspecialchars($user['email']) . ".";
    } else {
        $error = "Couldn't resend the code. Please try again in a moment.";
    }
}

// Handle code submission
if (isset($_POST['verify'])) {
    $enteredCode = trim($_POST['code'] ?? '');

    $stmt = $conn->prepare("SELECT verification_code, verification_expires FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row['verification_code'] || $enteredCode !== $row['verification_code']) {
        $error = "That code is incorrect. Please check your email and try again.";
    } elseif (strtotime($row['verification_expires']) < time()) {
        $error = "That code has expired. Tap \"Resend code\" below to get a new one.";
    } else {
        $upd = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();

        // Now log them in for real
        $_SESSION['user_id'] = $userId;
        $_SESSION['name'] = $user['name'];
        $_SESSION['just_registered'] = true;
        unset($_SESSION['pending_verification_user_id']);

        $timezone = $_SESSION['pending_verification_timezone'] ?? ($user['timezone'] ?: 'UTC');
        unset($_SESSION['pending_verification_timezone']);
        sfUpdateStreakOnLogin($conn, $userId, $timezone);

        header("Location: onboarding.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your email — StudyFlow</title>
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

    <h1>One last step.</h1>
    <p class="sf-auth-sub">We sent a 6-digit code to <strong><?php echo htmlspecialchars($user['email']); ?></strong>. Enter it below to activate your account.</p>

    <div class="sf-auth-card">
        <h2>Verify your email</h2>
        <p class="sub">Check your inbox (and spam folder, just in case).</p>

        <?php if ($error): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="sf-alert" style="background:rgba(31,111,92,0.12);color:#1f6f5c;border:1px solid rgba(31,111,92,0.3);padding:10px 14px;border-radius:8px;margin-bottom:16px;"><?php echo $notice; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="sf-field">
                <label for="code">6-digit code</label>
                <input type="text" id="code" name="code" placeholder="000000" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" style="letter-spacing:6px;font-size:22px;text-align:center;">
            </div>
            <button type="submit" name="verify" class="btn-sf-gold w-100 mt-2">Verify & continue</button>
        </form>

        <form method="POST" style="margin-top:12px;">
            <button type="submit" name="resend" class="btn-sf-gold w-100" style="background:transparent;border:1px solid var(--gold-light,#c9a15a);color:var(--gold-light,#c9a15a);">Resend code</button>
        </form>

        <div class="sf-auth-switch">
            Wrong email? <a href="register.php">Start over</a>
        </div>
    </div>
</div>
</body>
</html>
