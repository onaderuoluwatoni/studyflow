<?php
session_start();
include 'config/db.php';
include 'config/ai.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in to take a quiz.']);
    exit();
}

$subject = trim($_POST['subject'] ?? '');
$difficulty = trim($_POST['difficulty'] ?? 'Easy');
$count = max(5, min(20, (int) ($_POST['count'] ?? 10)));
$exams = include 'includes/exam_goals.php';
$examCode = trim($_POST['exam'] ?? 'OTHER');
if (!array_key_exists($examCode, $exams)) $examCode = 'OTHER';
$examLabel = $exams[$examCode];

$allowedDifficulty = ['Easy', 'Intermediate', 'Hard', 'Scholar'];
if (!in_array($difficulty, $allowedDifficulty, true)) $difficulty = 'Easy';

if ($subject === '') {
    echo json_encode(['ok' => false, 'text' => 'Pick a subject first.']);
    exit();
}

// ---- Cache check first: instant, and saves free-tier Gemini quota ----
$cacheKey = md5(strtolower($subject . '|' . $difficulty . '|' . $count . '|' . $examCode));
$stmt = $conn->prepare("SELECT questions_json FROM quiz_cache WHERE cache_key = ? AND created_at > (NOW() - INTERVAL 3 DAY)");
$stmt->bind_param('s', $cacheKey);
$stmt->execute();
$cached = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($cached) {
    $questions = json_decode($cached['questions_json'], true);
    if (is_array($questions) && count($questions) > 0) {
        echo json_encode(['ok' => true, 'questions' => $questions, 'cached' => true]);
        exit();
    }
}

$difficultyGuide = [
    'Easy'         => 'basic recall / definitions, suitable for a secondary school student just starting the topic',
    'Intermediate' => 'applied understanding, suitable for a secondary school student preparing for WAEC/NECO/JAMB',
    'Hard'         => 'analytical, multi-step questions, suitable for a first/second-year university student',
    'Scholar'      => 'advanced, exam/interview-level questions that would challenge a final-year or postgraduate student',
];

$prompt = "Write exactly {$count} multiple-choice quiz questions on the subject \"{$subject}\" at {$difficulty} difficulty ({$difficultyGuide[$difficulty]}). "
    . ($examCode !== 'OTHER' ? "Frame the questions in the style and scope of the {$examLabel} exam where relevant. " : "")
    . "Cover a good spread of topics within the subject, not just one narrow area. Each question must have exactly 4 answer options with only one correct. "
    . "Respond with ONLY raw JSON (no markdown fences, no commentary) in exactly this shape: "
    . '[{"q":"question text","opts":["a","b","c","d"],"correct":0}] '
    . "where \"correct\" is the zero-based index of the right option in \"opts\".";

$systemPrompt = 'You are a question-bank generator. You only ever output valid JSON matching the requested schema — never prose, never markdown code fences.';

$result = callAI($prompt, $systemPrompt);
if (!$result['ok']) {
    // one retry — free-tier Gemini calls occasionally fail transiently
    $result = callAI($prompt, $systemPrompt);
}

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'text' => $result['text'] . ' (Tip: this usually means the free Gemini quota is briefly maxed out — wait a moment and try again.)']);
    exit();
}

$text = trim($result['text']);
$text = preg_replace('/^```(json)?/i', '', $text);
$text = preg_replace('/```$/', '', $text);
$text = trim($text);

$questions = json_decode($text, true);

if (!is_array($questions) || count($questions) === 0) {
    echo json_encode(['ok' => false, 'text' => 'Could not generate questions for that subject just now — try again.']);
    exit();
}

// Sanity-filter: keep only well-formed questions.
$clean = [];
foreach ($questions as $q) {
    if (!isset($q['q'], $q['opts'], $q['correct'])) continue;
    if (!is_array($q['opts']) || count($q['opts']) < 2) continue;
    $clean[] = [
        'q' => (string) $q['q'],
        'opts' => array_values(array_map('strval', $q['opts'])),
        'correct' => (int) $q['correct'],
    ];
}

if (count($clean) === 0) {
    echo json_encode(['ok' => false, 'text' => 'Could not generate questions for that subject just now — try again.']);
    exit();
}

// ---- Save to cache ----
$jsonOut = json_encode($clean);
$stmt = $conn->prepare("INSERT INTO quiz_cache (cache_key, subject, difficulty, count, questions_json)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE questions_json = VALUES(questions_json), created_at = CURRENT_TIMESTAMP");
$stmt->bind_param('sssis', $cacheKey, $subject, $difficulty, $count, $jsonOut);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'questions' => $clean, 'cached' => false]);
