<?php
session_start();
include 'config/db.php';

$error = "";
$success = false;

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot-password.php");
    exit();
}

$userId = (int) $_SESSION['reset_user_id'];

if (isset($_POST['reset'])) {
    $enteredCode = trim($_POST['code'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT reset_code, reset_expires FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['reset_code'] || $enteredCode !== $row['reset_code']) {
        $error = "That code is incorrect. Please check your email and try again.";
    } elseif (strtotime($row['reset_expires']) < time()) {
        $error = "That code has expired. Please request a new one.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords don't match.";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?");
        $upd->bind_param('si', $hashed, $userId);
        $upd->execute();
        $upd->close();

        unset($_SESSION['reset_user_id']);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password — StudyFlow</title>
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

    <h1>Set a new password.</h1>
    <p class="sf-auth-sub">Enter the code we emailed you along with your new password.</p>

    <div class="sf-auth-card">
        <h2>Reset password</h2>

        <?php if ($success): ?>
            <div class="sf-alert" style="background:rgba(31,111,92,0.12);color:#1f6f5c;border:1px solid rgba(31,111,92,0.3);padding:10px 14px;border-radius:8px;margin-bottom:16px;">
                Your password has been reset. You can now sign in.
            </div>
            <a href="login.php" class="btn-sf-gold w-100 mt-2" style="display:block;text-align:center;text-decoration:none;">Go to sign in</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="sf-alert sf-alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="sf-field">
                    <label for="code">6-digit code</label>
                    <input type="text" id="code" name="code" placeholder="000000" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" style="letter-spacing:6px;font-size:22px;text-align:center;">
                </div>
                <div class="sf-field">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" placeholder="Create a new password" required minlength="6">
                </div>
                <div class="sf-field">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required minlength="6">
                </div>
                <button type="submit" name="reset" class="btn-sf-gold w-100 mt-2">Reset password</button>
            </form>

            <div class="sf-auth-switch">
                Didn't get a code? <a href="forgot-password.php">Request again</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
