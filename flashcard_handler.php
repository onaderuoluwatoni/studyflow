<?php
session_start();
include 'config/db.php';
include 'config/ai.php';
include 'includes/coach.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in to create flashcards.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$term = trim($_POST['front'] ?? '');

if ($term === '') {
    echo json_encode(['ok' => false, 'text' => 'Type a word or concept first.']);
    exit();
}

$aiPrompt = "Create the back of a student flashcard for this term or concept: \"" . $term . "\". "
    . "Reply in EXACTLY this format, nothing else, no markdown symbols, no restating the term:\n"
    . "Line 1: one simple, plain-language sentence a beginner could understand — this is the definition.\n"
    . "Then a blank line.\n"
    . "Then 'Key points:' on its own line.\n"
    . "Then 3 short bullet points (start each with '- '), each a single memorable fact or detail about it.";

$result = callAI($aiPrompt, 'You write extremely concise, plain-language flashcard '
    . 'content that is as easy as possible to remember and recall under exam pressure. '
    . 'You always follow the exact format you are asked for, with no extra commentary.');

$back = $result['ok']
    ? trim($result['text'])
    : 'Could not generate a meaning right now: ' . $result['text'] . ' You can delete this card and try again.';

$stmt = $conn->prepare("INSERT INTO flashcards (user_id, front, back) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $user_id, $term, $back);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();

sfAdvanceDailyChallenge($conn, $user_id, 'flashcard', 1);

echo json_encode([
    'ok' => true,
    'id' => $newId,
    'front' => $term,
    'back' => $back,
    'aiOk' => $result['ok'],
]);
