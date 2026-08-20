<?php
session_start();
include 'config/db.php';
include 'config/ai.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'gate' => 'login', 'text' => 'Please sign in to use the AI Tutor.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$_SESSION['ai_question_count'] = $_SESSION['ai_question_count'] ?? 0;
$_SESSION['ai_image_count'] = $_SESSION['ai_image_count'] ?? 0;

$hasImage = isset($_POST['hasImage']) && $_POST['hasImage'] === '1';
$message = trim($_POST['message'] ?? '');
$convId = isset($_POST['conversationId']) ? (int) $_POST['conversationId'] : 0;

// imageData arrives as a data URL (e.g. "data:image/png;base64,....") from the
// browser's FileReader — split it into mime type + raw base64 for Gemini.
$imageDataUrl = $_POST['imageData'] ?? '';
$imagePart = null;
if ($hasImage && $imageDataUrl && preg_match('/^data:([\w\/\-\.]+);base64,(.+)$/', $imageDataUrl, $m)) {
    $imagePart = ['mime_type' => $m[1], 'data' => $m[2]];
}

// AI Tutor questions are unlimited for every user. File uploads are capped
// at 10 per session to keep image-analysis costs in check.
if ($hasImage && $_SESSION['ai_image_count'] >= 10) {
    echo json_encode([
        'ok' => false,
        'gate' => 'premium_images',
        'text' => "You've used your 10 file uploads for this session. Start a new session or come back later to upload more."
    ]);
    exit();
}

if ($message === '') {
    echo json_encode(['ok' => false, 'text' => 'Please type a question.']);
    exit();
}

// Make sure any conversationId we were given actually belongs to this user.
if ($convId > 0) {
    $check = $conn->query("SELECT id FROM ai_conversations WHERE id='$convId' AND user_id='$user_id'");
    if (!$check || $check->num_rows === 0) $convId = 0;
}

// Lazily create the conversation on the first message, ChatGPT/Claude-style,
// so browsing to the AI Tutor never litters the sidebar with empty chats.
$isNewConversation = false;
if ($convId === 0) {
    $titleSrc = mb_substr($message, 0, 60);
    $titleEsc = $conn->real_escape_string($titleSrc);
    $conn->query("INSERT INTO ai_conversations (user_id, title) VALUES ('$user_id', '$titleEsc')");
    $convId = $conn->insert_id;
    $isNewConversation = true;
}

$mode = $_POST['mode'] ?? 'ask';
$prompts = [
    'ask' => 'Answer this student question clearly and directly: ',
    'explain' => 'Explain this topic simply, building from the basics before the formal definition: ',
    'summarize' => 'Summarize the following notes into concise key points: ',
    'quiz' => 'Generate a short 5-question quiz (with answers at the end) on: ',
    'math' => 'Solve this step-by-step, showing each stage of working clearly: ',
];
$finalPrompt = ($prompts[$mode] ?? $prompts['ask']) . $message;
if ($imagePart) {
    $finalPrompt .= "\n\n(The student also attached an image — look at it as part of answering.)";
}

// Save the user's message first so it's never lost even if the AI call fails.
$msgEsc = $conn->real_escape_string($message);
$imgColEsc = $imageDataUrl ? "'" . $conn->real_escape_string($imageDataUrl) . "'" : 'NULL';
$conn->query("INSERT INTO ai_messages (conversation_id, role, content, image_data_url) VALUES ('$convId', 'user', '$msgEsc', $imgColEsc)");

$result = callAI($finalPrompt, '', $imagePart);

// Only count usage toward the free-tier limits on a successful, real answer
if ($result['ok']) {
    $_SESSION['ai_question_count']++;
    if ($hasImage) $_SESSION['ai_image_count']++;
}

$aiTextEsc = $conn->real_escape_string($result['text']);
$conn->query("INSERT INTO ai_messages (conversation_id, role, content) VALUES ('$convId', 'ai', '$aiTextEsc')");
$conn->query("UPDATE ai_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id='$convId'");

echo json_encode([
    'ok' => $result['ok'],
    'text' => $result['text'],
    'conversationId' => $convId,
    'isNewConversation' => $isNewConversation,
    'questionsUsed' => $_SESSION['ai_question_count'],
    'imagesUsed' => $_SESSION['ai_image_count'],
]);
