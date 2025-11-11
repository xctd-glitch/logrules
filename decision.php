<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/_bootstrap.php';

use function SRP\env;
use function SRP\decisionResponse;
use function SRP\logHit;
use function SRP\readLimitedStream;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$origin   = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowEnv = trim((string) env('SRP_CORS_ALLOW_ORIGIN', ''));
if ($origin !== '' && $allowEnv !== '') {
    $allows = preg_split('~\s*,\s*~', $allowEnv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($allows as $o) {
        if ($o === '*' || strcasecmp($origin, $o) === 0) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Max-Age: 600');
            break;
        }
    }
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
if ($method === 'OPTIONS') { http_response_code(204); return; }
if ($method !== 'POST') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }

$apiKey = env('SRP_API_KEY', '');
if ($apiKey === '') { http_response_code(500); echo '{"ok":false,"error":"server_misconfigured"}'; return; }
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) { http_response_code(401); echo '{"ok":false,"error":"unauthorized"}'; return; }

$ct  = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (stripos($ct, 'application/json') !== 0) { http_response_code(415); echo '{"ok":false,"error":"unsupported_media_type"}'; return; }
if ($len > 8192 && $len > 0) { http_response_code(413); echo '{"ok":false,"error":"payload_too_large"}'; return; }

$maxPayload = 8192;
$rawInfo = readLimitedStream('php://input', $maxPayload);
[$raw, $overflowed] = $rawInfo;

if ($overflowed) {
    http_response_code(413);
    echo '{"ok":false,"error":"payload_too_large"}';

    return;
}

try {
    $in = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    http_response_code(400);
    echo '{\"ok\":false,\"error\":\"invalid_json\"}';
    return;
}
if (!is_array($in)) { $in = []; }

$result = decisionResponse($in);
$response = $result['response'];
$response['ok'] = true;

echo json_encode($response, JSON_UNESCAPED_SLASHES);

try {
    logHit($result['log']);
} catch (\Throwable $e) {
    // ignore logging errors
}
