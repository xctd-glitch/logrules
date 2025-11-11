<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/_bootstrap.php';

use function SRP\env;
use function SRP\cfg;
use function SRP\cfgUpdate;

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
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Max-Age: 600');
            break;
        }
    }
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
if ($method === 'OPTIONS') { http_response_code(204); return; }

$apiKey = env('SRP_API_KEY', '');
if ($apiKey === '') { http_response_code(500); echo '{"ok":false,"error":"server_misconfigured"}'; return; }
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) { http_response_code(401); echo '{"ok":false,"error":"unauthorized"}'; return; }

if ($method === 'GET') { echo json_encode(['ok'=>true,'config'=>cfg()], JSON_UNESCAPED_SLASHES); return; }
if ($method !== 'POST') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }

$ct  = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (stripos($ct, 'application/json') !== 0) { http_response_code(415); echo '{"ok":false,"error":"unsupported_media_type"}'; return; }
if ($len > 8192 && $len > 0) { http_response_code(413); echo '{"ok":false,"error":"payload_too_large"}'; return; }

$raw = (string) (@file_get_contents('php://input', false, null, 0, 8192) ?: '');
$in  = json_decode($raw, true);
if (!is_array($in)) { $in = []; }

$partial = [];
if (array_key_exists('system_on', $in)) { $partial['system_on'] = (bool) $in['system_on']; }
if (array_key_exists('redirect_url', $in) && is_string($in['redirect_url'])) { $partial['redirect_url'] = $in['redirect_url']; }
if (array_key_exists('allowed_countries', $in)) { $partial['allowed_countries'] = $in['allowed_countries']; }
if (array_key_exists('is_active', $in)) { $partial['is_active'] = (bool) $in['is_active']; }
if (array_key_exists('rule_mode', $in) && is_string($in['rule_mode'])) { $partial['rule_mode'] = $in['rule_mode']; }

try {
    $out = cfgUpdate($partial);
    echo json_encode(['ok'=>true,'config'=>$out], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo '{"ok":false,"error":"update_failed"}';
}