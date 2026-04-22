<?php
/**
 * Diagnostic: test Gemini API connectivity and key (no key shown in output).
 * Call from browser: http://localhost/newbot/api/test_gemini.php
 */
header('Content-Type: application/json; charset=utf-8');

$result = ['ok' => false, 'message' => '', 'detail' => ''];

try {
    require_once __DIR__ . '/../config.php';
} catch (Throwable $e) {
    $result['message'] = 'Config failed';
    $result['detail'] = $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$key = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
if ($key === '' || $key === 'YOUR_NEW_API_KEY_HERE') {
    $result['message'] = 'No API key set. Edit config.php and set GEMINI_API_KEY.';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$result['message'] = 'Key is set (' . strlen($key) . ' chars). Testing request…';

$url = (defined('GEMINI_API_URL') ? GEMINI_API_URL : 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent') . '?key=' . $key;
$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => 'Reply with exactly: OK']]],
    ],
];

if (!function_exists('curl_init')) {
    $result['detail'] = 'cURL is not enabled in PHP. Enable php_curl in php.ini.';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    $result['detail'] = 'cURL error: ' . ($curlErr ?: 'unknown');
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($response, true);

if (!empty($data['error']['message'])) {
    $result['message'] = 'API returned an error';
    $result['detail'] = $data['error']['message'];
    $result['http_code'] = $httpCode;
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
if (is_string($text) && trim($text) !== '') {
    $result['ok'] = true;
    $result['message'] = 'Gemini is reachable and the key works.';
} else {
    $result['detail'] = 'Unexpected response structure. HTTP ' . $httpCode;
}
echo json_encode($result, JSON_PRETTY_PRINT);
