<?php
session_start();
include '../config/db.php';
include '../includes/coach.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$id = (int) $_GET['id'];
$user_id = (int) $_SESSION['user_id'];

/* 1. Mark task as completed */
$stmt = $conn->prepare("UPDATE tasks SET status='completed' WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $user_id);
$stmt->execute();
$stmt->close();

/* 1b. Award +5 XP for completing the task */
$stmt = $conn->prepare("UPDATE users SET xp = xp + 5 WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->close();

/* 1c. Count this toward today's daily challenge, if it's a task challenge */
sfAdvanceDailyChallenge($conn, $user_id, 'task', 1);

/* Streak days are counted only when the user logs into the site (see
   includes/streak.php, called from login.php / register.php), using their
   own timezone — task completion no longer touches the streak. */

header("Location: ../index.php");
exit();
?>