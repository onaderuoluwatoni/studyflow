<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$message = "";

if (isset($_POST['add'])) {

    $subject = trim($_POST['subject']);
    $user_id = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO subjects (user_id, subject_name) VALUES (?, ?)");
    $stmt->bind_param('is', $user_id, $subject);

    if ($stmt->execute()) {
        $message = "Subject added.";
    } else {
        $message = "Error: " . $conn->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyFlow — Add subject</title>
    <link rel="icon" href="../assets/img/logo.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="sf-navbar">
    <div class="sf-brand">
        <img src="../assets/img/logo.svg" alt="StudyFlow logo">
        <span class="sf-brand-text">Study<span style="color:var(--gold-light)">Flow</span></span>
    </div>
    <a href="../index.php" class="btn-sf-outline" style="border-color:rgba(255,255,255,0.25); color:#fff;">Back to dashboard</a>
</nav>

<div class="container d-flex justify-content-center mt-5">
    <div class="sf-auth-card" style="max-width:440px;">
        <h2>Add a subject</h2>
        <p class="sub">Give it a clear name so it's easy to file tasks under it later.</p>

        <?php if ($message): ?>
            <div class="sf-alert <?php echo strpos($message, 'Error') === 0 ? 'sf-alert-error' : 'sf-alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="sf-field">
                <label for="subject">Subject name</label>
                <input type="text" id="subject" name="subject" placeholder="e.g. Organic Chemistry" required>
            </div>
            <button type="submit" name="add" class="btn-sf-gold w-100 mt-2">Add subject</button>
        </form>
    </div>
</div>

</body>
</html>
