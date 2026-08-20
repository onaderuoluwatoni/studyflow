<?php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studyflow";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto sign-out after 30 minutes of inactivity. Runs on every page that
// includes this file (which is effectively every authenticated page),
// so it doesn't need to be added to each page individually.
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $timeoutSeconds = 30 * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

?>