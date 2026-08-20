<?php
/**
 * StudyFlow AI Tutor configuration — Google Gemini backend.
 *
 * The real API key lives in config/ai_key.local.php, which is listed in
 * .gitignore and NEVER committed to source control. This file just wires
 * it in, so config/ai.php itself is safe to have in a public repo.
 *
 * First-time setup: copy config/ai_key.example.php to
 * config/ai_key.local.php and put your real Gemini key in it.
 */
if (file_exists(__DIR__ . '/ai_key.local.php')) {
    include __DIR__ . '/ai_key.local.php';
} elseif (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
}

/**
 * gemini-2.5-flash was retired for new users, so this uses the current
 * "gemini-flash-latest" alias, which Google automatically points at their
 * newest stable Flash model, so you won't hit a dead-model error again.
 */
define('GEMINI_MODEL', 'gemini-flash-latest');

/**
 * Calls the Gemini API and returns the assistant's text reply.
 *
 * $imagePart (optional): ['mime_type' => 'image/png', 'data' => '<base64 without prefix>']
 * lets a question include an uploaded image (e.g. a photo of a homework
 * question) — Gemini looks at the image and the text together.
 *
 * Returns ['ok' => bool, 'text' => string].
 */
function callAI($userMessage, $systemPrompt = '', $imagePart = null) {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        return [
            'ok' => false,
            'text' => "The AI Tutor isn't connected to a live model yet — add your Gemini API key in config/ai.php to activate real answers."
        ];
    }

    $systemPrompt = $systemPrompt ?: 'You are a patient, encouraging study tutor for secondary and university students. Explain concepts clearly, show step-by-step working for maths and science problems, and keep answers focused and exam-relevant.';

    $parts = [];
    if ($imagePart && !empty($imagePart['data'])) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $imagePart['mime_type'] ?: 'image/jpeg',
                'data' => $imagePart['data'],
            ],
        ];
    }
    $parts[] = ['text' => $userMessage];

    $payload = [
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [
            ['role' => 'user', 'parts' => $parts],
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return ['ok' => false, 'text' => 'Could not reach the AI service right now. Please try again shortly.'];
    }

    $data = json_decode($response, true);

    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return ['ok' => true, 'text' => $data['candidates'][0]['content']['parts'][0]['text']];
    }

    if (isset($data['error']['message'])) {
        return ['ok' => false, 'text' => 'The AI service returned an error: ' . $data['error']['message']];
    }

    return ['ok' => false, 'text' => 'The AI service returned an unexpected response.'];
}
