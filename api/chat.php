<?php
/**
 * Chat API: Database-first, Gemini fallback
 * POST: { "message": "user question" }
 * Returns: { "answer": "...", "source": "database"|"gemini"|"error" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../database.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Config error: ' . $e->getMessage(), 'answer' => null]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = $rawInput ? json_decode($rawInput, true) : null;
$message = trim((string) (is_array($input) ? ($input['message'] ?? '') : ''));

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

try {
    // --- Step 1: Try database (keyword-based lookup) ---
    $answerFromDb = findAnswerInDatabase($message);

    if ($answerFromDb !== null && !empty($answerFromDb['answer'])) {
        logChat($message, (string) $answerFromDb['answer'], 'database');
        echo json_encode([
            'answer'  => $answerFromDb['answer'],
            'source'  => 'database',
            'matched_question' => $answerFromDb['question'] ?? null,
        ]);
        exit;
    }

    // --- Step 2: Fallback to Gemini AI ---
    $geminiResult = askGemini($message);

    if (!empty($geminiResult['success']) && !empty($geminiResult['text'])) {
        logChat($message, $geminiResult['text'], 'gemini');
        echo json_encode([
            'answer'  => $geminiResult['text'],
            'source'  => 'gemini',
        ]);
        exit;
    }

    // No DB match and Gemini failed — return specific reason so user can fix it
    $reason = $geminiResult['reason'] ?? 'unknown';
    $detail = $geminiResult['detail'] ?? '';
    $fallbackMessage = getGeminiUnavailableMessage($reason, $detail);
    echo json_encode([
        'answer'        => $fallbackMessage,
        'source'        => 'error',
        'gemini_reason' => $reason,
        'gemini_detail' => $detail,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'  => 'Server error: ' . $e->getMessage(),
        'answer' => null,
    ]);
}

/**
 * Keyword-based search: match user message against keywords and question text
 */
function findAnswerInDatabase(string $userMessage): ?array {
    global $conn;
    $userMessage = trim($userMessage);
    if ($userMessage === '') return null;

    $normalized = preg_replace('/\s+/', ' ', strtolower($userMessage));
    $words = array_filter(explode(' ', $normalized), function ($w) { return strlen($w) >= 2; });
    if (empty($words)) return null;

    $conditions = [];
    $params = [];
    foreach (array_slice($words, 0, 10) as $word) {
        $conditions[] = '(keywords LIKE ? OR question LIKE ?)';
        $params[] = '%' . $word . '%';
        $params[] = '%' . $word . '%';
    }
    $sql = 'SELECT question, answer, category FROM knowledge_base WHERE ' . implode(' OR ', $conditions) . ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * Call Gemini API (fallback when DB has no match).
 * Returns array: ['success' => true, 'text' => '...'] or ['success' => false, 'reason' => '...', 'detail' => '...']
 * Uses GEMINI_API_KEY from config.php.
 */
function askGemini(string $userMessage): array {
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if ($apiKey === '' || $apiKey === 'YOUR_NEW_API_KEY_HERE') {
        return ['success' => false, 'reason' => 'no_key', 'detail' => 'Set GEMINI_API_KEY in config.php (get key at https://aistudio.google.com/apikey)'];
    }

    // Same as working reference: v1 + gemini-2.5-flash, key in URL
    $baseUrl = defined('GEMINI_API_URL') ? GEMINI_API_URL : 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent';
    $url = $baseUrl . '?key=' . $apiKey;

    // Payload structure matching working code: role "user" + prompt format
    $payload = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [
                    ['text' => 'You are an educational assistant. Keep answers concise and helpful.' . "\n\nUser: " . $userMessage],
                ],
            ],
        ],
    ];

    $json = json_encode($payload);
    if ($json === false) {
        return ['success' => false, 'reason' => 'request_failed', 'detail' => 'Invalid request payload'];
    }

    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,  // Required for XAMPP/WAMP local dev; set true in production
            ]);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($response === false || $response === null || $response === '') {
                return ['success' => false, 'reason' => 'request_failed', 'detail' => $curlError ?: 'cURL request failed'];
            }
        }
    }

    if ($response === null || $response === '') {
        $response = geminiFallbackStream($url, $json);
    }
    if ($response === null || $response === '') {
        return ['success' => false, 'reason' => 'request_failed', 'detail' => 'HTTP request failed. Enable cURL in PHP (php.ini: extension=curl) or check firewall.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'reason' => 'api_error', 'detail' => 'Invalid response from API'];
    }

    if (!empty($data['error']['message'])) {
        return ['success' => false, 'reason' => 'api_error', 'detail' => $data['error']['message']];
    }

    $text = isset($data['candidates'][0]['content']['parts'][0]['text'])
        ? $data['candidates'][0]['content']['parts'][0]['text']
        : null;
    if (is_string($text) && trim($text) !== '') {
        return ['success' => true, 'text' => trim($text)];
    }

    return ['success' => false, 'reason' => 'empty_response', 'detail' => 'API returned no text (model may have blocked the response)'];
}

/**
 * Human-readable message when Gemini is unavailable.
 */
function getGeminiUnavailableMessage(string $reason, string $detail): string {
    $base = 'I could not find an answer in my database and the AI fallback is unavailable. ';
    switch ($reason) {
        case 'no_key':
            return $base . 'Add your Gemini API key in config.php (set GEMINI_API_KEY). Get a free key at https://aistudio.google.com/apikey';
        case 'request_failed':
            return $base . 'Request failed: ' . ($detail ?: 'check internet connection and that cURL is enabled in PHP.');
        case 'api_error':
            return $base . 'Google API error: ' . ($detail ?: 'check your API key and quota.');
        case 'empty_response':
            return $base . 'The API returned no text. Try a different question.';
        default:
            return $base . ($detail ? 'Details: ' . $detail : 'Check config.php and API key.');
    }
}

/**
 * Fallback when cURL is not available: use stream context for HTTPS.
 */
function geminiFallbackStream(string $url, string $json): ?string {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'   => true,
        ],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    return ($result !== false) ? $result : null;
}

function logChat(string $userMessage, string $botResponse, string $source): void {
    global $conn;
    try {
        $stmt = $conn->prepare('INSERT INTO chat_log (user_message, bot_response, source) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('sss', $userMessage, $botResponse, $source);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Ignore log errors
    }
}
