<?php
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$exams = include 'includes/exam_goals.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goal = $_POST['exam_goal'] ?? '';
    if (!array_key_exists($goal, $exams)) $goal = 'OTHER';

    $stmt = $conn->prepare("UPDATE users SET exam_goal = ? WHERE id = ?");
    $stmt->bind_param('si', $goal, $user_id);
    $stmt->execute();
    $stmt->close();

    if ($goal === 'PRIMARY_SCHOOL') {
        header("Location: primary-coming-soon.php");
        exit();
    }

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — What are you reading for?</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=2" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1526">
<link rel="apple-touch-icon" href="assets/img/icons/apple-touch-icon.png">
</head>
<body class="sf-theme-deep">
<script>
(function(){
  var t = localStorage.getItem('sf-theme') || 'dark';
  if (t === 'dark') document.body.classList.add('sf-theme-deep');
})();
</script>
<div class="sf-auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div class="sf-feature-card" style="max-width:480px;width:100%;">
        <h3 class="mb-2">What are you reading for?</h3>
        <p class="text-muted mb-3">This helps us tailor your quizzes, past questions, and dashboard to what you're actually preparing for.</p>
        <form method="POST">
            <div class="d-flex flex-column gap-2">
                <?php foreach ($exams as $code => $label): ?>
                    <button type="submit" name="exam_goal" value="<?php echo $code; ?>" class="sf-pill" style="text-align:left;width:100%;padding:12px 16px;">
                        <?php echo htmlspecialchars($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>
</body>
</html>
