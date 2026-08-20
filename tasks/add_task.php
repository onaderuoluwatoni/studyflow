<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$message = "";

if (isset($_POST['add'])) {

    $task_title = trim($_POST['task_title']);
    $subject_name = trim($_POST['subject_name'] ?? '');
    $due_date = $_POST['due_date'];

    $subject_id = null;
    if ($subject_name !== '') {
        // Reuse an existing subject with this name for this user, or create it.
        $find = $conn->prepare("SELECT id FROM subjects WHERE user_id = ? AND subject_name = ?");
        $find->bind_param('is', $user_id, $subject_name);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();

        if ($found) {
            $subject_id = (int) $found['id'];
        } else {
            $ins = $conn->prepare("INSERT INTO subjects (user_id, subject_name) VALUES (?, ?)");
            $ins->bind_param('is', $user_id, $subject_name);
            $ins->execute();
            $subject_id = $conn->insert_id;
            $ins->close();
        }
    }

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, task_title, due_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iiss', $user_id, $subject_id, $task_title, $due_date);

    if ($stmt->execute()) {
        $message = "Task added.";
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
    <title>StudyFlow — Add task</title>
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
    <div class="sf-auth-card" style="max-width:460px;">
        <h2>Add a task</h2>
        <p class="sub">Attach it to a subject and set a due date to stay on track.</p>

        <?php if ($message): ?>
            <div class="sf-alert <?php echo strpos($message, 'Error') === 0 ? 'sf-alert-error' : 'sf-alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="sf-field">
                <label for="task_title">Task title</label>
                <input type="text" id="task_title" name="task_title" placeholder="e.g. Revise chapter 4" required>
            </div>

            <div class="sf-field">
                <label for="subject_name">Subject</label>
                <input type="text" id="subject_name" name="subject_name" placeholder="e.g. Mathematics" required list="subjectSuggestions">
                <datalist id="subjectSuggestions">
                    <?php
                    $sstmt = $conn->prepare("SELECT DISTINCT subject_name FROM subjects WHERE user_id = ?");
                    $sstmt->bind_param('i', $user_id);
                    $sstmt->execute();
                    $subjects = $sstmt->get_result();
                    while ($s = $subjects->fetch_assoc()) {
                    ?>
                        <option value="<?php echo htmlspecialchars($s['subject_name']); ?>">
                    <?php } ?>
                </datalist>
            </div>

            <div class="sf-field">
                <label for="due_date">Due date</label>
                <input type="date" id="due_date" name="due_date" required>
            </div>

            <button type="submit" name="add" class="btn-sf-gold w-100 mt-2">Add task</button>
        </form>
    </div>
</div>

</body>
</html>
