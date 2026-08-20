<?php
session_start();
include 'config/db.php';
include 'config/ai.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'text' => 'Please sign in to view past questions.']);
    exit();
}

$subject = trim($_POST['subject'] ?? '');
$topic = trim($_POST['topic'] ?? '');
$year = trim($_POST['year'] ?? '');
$level = trim($_POST['level'] ?? 'Secondary');
$university = trim($_POST['university'] ?? '');
$exams = include 'includes/exam_goals.php';
$examCode = trim($_POST['exam'] ?? 'OTHER');
if (!array_key_exists($examCode, $exams)) $examCode = 'OTHER';
$examLabel = $exams[$examCode];

if ($subject === '') {
    echo json_encode(['ok' => false, 'text' => 'Choose a subject first.']);
    exit();
}

// ---- Check the cache first (instant, and doesn't burn Gemini free-tier quota) ----
$cacheKey = md5(strtolower($subject . '|' . $topic . '|' . $year . '|' . $level . '|' . $university . '|' . $examCode));

$stmt = $conn->prepare("SELECT questions_json FROM pq_cache WHERE cache_key = ? AND created_at > (NOW() - INTERVAL 3 DAY)");
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

// ---- Not cached: ask the AI, with one retry since free-tier calls sometimes ----
// ---- fail transiently — this is also WHY every year used to look the same: ----
// ---- a failed call used to silently fall back to one fixed static list.    ----
$topicLine = $topic !== '' ? " focused specifically on the topic \"{$topic}\"" : " covering a broad spread of the syllabus";
$yearLine = $year !== '' ? " written in the style and difficulty of a {$year} exam paper" : "";
$uniLine = ($level === 'University' && $university !== '' && $university !== 'Other / not listed')
    ? " Frame the questions the way they'd typically be asked at {$university} for this course."
    : "";

$prompt = "Write 8 realistic, DISTINCT past-exam-style practice questions for the subject \"{$subject}\" at {$level} level{$topicLine}{$yearLine}.{$uniLine} "
    . ($examCode !== 'OTHER' ? "These should match the format, scope, and difficulty of the {$examLabel} exam. " : "")
    . "Mix short-answer and structured/essay-style questions as appropriate for the subject, the way they'd appear in a real past exam paper. "
    . "Do not repeat the same question twice. Vary phrasing and sub-topics across the 8 questions so they don't all test the same narrow point. "
    . "Respond with ONLY raw JSON (no markdown fences, no commentary): [\"question 1\", \"question 2\", ...]";

$result = callAI($prompt, 'You are an exam-question archive. You only ever output valid JSON — a plain array of question strings, nothing else.');

if (!$result['ok']) {
    // one retry — free-tier Gemini calls occasionally fail transiently
    $result = callAI($prompt, 'You are an exam-question archive. You only ever output valid JSON — a plain array of question strings, nothing else.');
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
    echo json_encode(['ok' => false, 'text' => 'Could not fetch past questions for that just now — try again.']);
    exit();
}

$clean = array_values(array_filter(array_map('strval', $questions), fn($q) => trim($q) !== ''));

if (count($clean) === 0) {
    echo json_encode(['ok' => false, 'text' => 'Could not fetch past questions for that just now — try again.']);
    exit();
}

// ---- Save to cache for next time ----
$jsonOut = json_encode($clean);
$stmt = $conn->prepare("INSERT INTO pq_cache (cache_key, subject, topic, year, level, university, questions_json)
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE questions_json = VALUES(questions_json), created_at = CURRENT_TIMESTAMP");
$stmt->bind_param('sssssss', $cacheKey, $subject, $topic, $year, $level, $university, $jsonOut);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'questions' => $clean, 'cached' => false]);
