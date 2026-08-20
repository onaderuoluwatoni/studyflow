<?php
session_start();
include 'config/db.php';
include 'includes/mailer.php';

$error = "";
$notice = "";

if (isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $code = sfGenerateCode();
        $expires = date('Y-m-d H:i:s', time() + 15 * 60);

        $upd = $conn->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?");
        $upd->bind_param('ssi', $code, $expires, $user['id']);
        $upd->execute();
        $upd->close();

        $mailError = null;
        $sent = sfSendResetEmail($user['email'], $user['name'], $code, $mailError);

        if ($sent) {
            $notice = "A reset code has been sent to your email.";
        } else {
            $error = "Couldn't send the reset email. Please try again in a moment.";
            if ($mailError) {
                $error .= " (" . $mailError . ")";
            }
        }

        $_SESSION['reset_user_id'] = $user['id'];
    } else {
        // Don't reveal whether the email exists — show the same success message either way
        $notice = "A reset code has been sent to your email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password — StudyFlow</title>
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

    <h1>Forgot your password?</h1>
    <p class="sf-auth-sub">Enter the email on your account and we'll send you a code to reset it.</p>

    <div class="sf-auth-card">
        <h2>Reset password</h2>

        <?php if ($error): ?>
            <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="sf-alert" style="background:rgba(31,111,92,0.12);color:#1f6f5c;border:1px solid rgba(31,111,92,0.3);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                <?php echo htmlspecialchars($notice); ?>
                <div style="margin-top:8px;"><a href="reset-password.php" style="color:#1f6f5c;font-weight:600;">Enter code →</a></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="sf-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <button type="submit" name="send_reset" class="btn-sf-gold w-100 mt-2">Send reset code</button>
        </form>

        <div class="sf-auth-switch">
            Remembered it? <a href="login.php">Back to sign in</a>
        </div>
    </div>
</div>
</body>
</html>
