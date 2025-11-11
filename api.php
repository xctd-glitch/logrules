<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/_bootstrap.php';

use function SRP\env;
use function SRP\db;
use function SRP\cfg;
use function SRP\decisionResponse;
use function SRP\logHit;
use function SRP\readLimitedStream;

// ===== Common headers
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

// ===== CORS allow-list
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
if ($method === 'OPTIONS') { http_response_code(204); exit; }

// ===== Auth (X-API-Key)
$apiKey = env('SRP_API_KEY', '');
if ($apiKey === '') { http_response_code(500); echo '{"ok":false,"error":"server_misconfigured"}'; exit; }
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_SLASHES);
    exit;
}

// ===== Routing (/api/v1/..)
$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path = trim($path, '/');
// if using /api.php?path=..., prefer given path
if (isset($_GET['path']) && is_string($_GET['path'])) { $path = trim($_GET['path'], '/'); }

// Accept both /api/v1/* and v1/*
if (str_starts_with($path, 'api/')) { $path = substr($path, 4); }
if (str_starts_with($path, 'v1/'))  { $path = substr($path, 3); }

switch ($path) {
    case 'health':
        route_health($method);
        break;
    case 'decision':
        route_decision($method);
        break;
    case 'config':
        route_config($method);
        break;
    case 'stats':
        route_stats($method);
        break;
    case 'clicks':
        route_clicks($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'not_found','path'=>$path], JSON_UNESCAPED_SLASHES);
}
exit;

// =====================================================================
// Handlers
// =====================================================================

/**
 * GET /api/v1/health
 */
function route_health(string $method): void
{
    if ($method !== 'GET') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }
    echo json_encode([
        'ok' => true,
        'name' => 'srp-api',
        'version' => '1.0.0',
        'time' => time(),
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * POST /api/v1/decision
 * Body JSON: {click_id,country_code,user_agent,ip_address,user_lp}
 */
function route_decision(string $method): void
{
    if ($method !== 'POST') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }

    $ct  = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $len = (int)    ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (stripos($ct, 'application/json') !== 0) { http_response_code(415); echo '{"ok":false,"error":"unsupported_media_type"}'; return; }
    if ($len > 8192 && $len > 0) { http_response_code(413); echo '{"ok":false,"error":"payload_too_large"}'; return; }

    $maxPayload = 8192;
    [$raw, $overflowed] = readLimitedStream('php://input', $maxPayload);

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
}

/**
 * GET /api/v1/config  (read-only for clients)
 */
function route_config(string $method): void
{
    if ($method !== 'GET') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }
    $c = cfg();
    echo json_encode(['ok'=>true,'config'=>$c], JSON_UNESCAPED_SLASHES);
}

/**
 * GET /api/v1/stats?window=15
 * Returns totals for last N minutes + top countries + per-minute series.
 */
function route_stats(string $method): void
{
    if ($method !== 'GET') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }
    $window = (int) ($_GET['window'] ?? 15);
    if ($window < 1) { $window = 1; }
    if ($window > 60) { $window = 60; }

    $pdo = db();
    $threshold = time() - ($window * 60);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total, SUM(decision='A') AS a, SUM(decision='B') AS b
           FROM hits WHERE created_at >= FROM_UNIXTIME(:threshold)"
    );
    $stmt->bindValue(':threshold', $threshold, \PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch() ?: [];
    $total = (int) ($row['total'] ?? 0);
    $a = (int) ($row['a'] ?? 0);
    $b = (int) ($row['b'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT cc, COUNT(*) AS c FROM hits
           WHERE created_at >= FROM_UNIXTIME(:threshold)
           GROUP BY cc ORDER BY c DESC LIMIT 5"
    );
    $stmt->bindValue(':threshold', $threshold, \PDO::PARAM_INT);
    $stmt->execute();
    /** @var list<array{cc:string,c:int}> $top */
    $top = [];
    while ($r = $stmt->fetch()) {
        $top[] = [
            'cc' => substr(strtoupper((string) $r['cc']), 0, 2),
            'c' => (int) $r['c'],
        ];
    }

    $limit = max(1, min($window, 60));
    $sql = "SELECT FLOOR(UNIX_TIMESTAMP(created_at)/60)*60 AS ts, COUNT(*) AS c
              FROM hits WHERE created_at >= FROM_UNIXTIME(:threshold)
              GROUP BY ts ORDER BY ts ASC LIMIT " . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':threshold', $threshold, \PDO::PARAM_INT);
    $stmt->execute();
    /** @var list<array{ts:int,c:int}> $series */
    $series = [];
    while ($r = $stmt->fetch()) {
        $series[] = [
            'ts' => (int) $r['ts'],
            'c' => (int) $r['c'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'window' => $window,
        'total' => $total,
        'a' => $a,
        'b' => $b,
        'top_countries' => $top,
        'series' => $series,
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * GET /api/v1/clicks?after_id=0&timeout=20  (long-poll)
 */
function route_clicks(string $method): void
{
    if ($method !== 'GET') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; return; }
    $afterId = (int) ($_GET['after_id'] ?? 0); if ($afterId < 0) { $afterId = 0; }
    $timeout = (int) ($_GET['timeout'] ?? 20); if ($timeout < 1) { $timeout = 1; } if ($timeout > 25) { $timeout = 25; }

    $pdo = db();
    $deadline = time() + $timeout;

    $batch = fetch_after($pdo, $afterId, 200);
    if (count($batch['hits']) === 0) {
        while (time() < $deadline) {
            usleep(250000);
            $batch = fetch_after($pdo, $afterId, 200);
            if (count($batch['hits']) > 0) { break; }
        }
    }

    echo json_encode([
        'ok' => true,
        'server_time' => time(),
        'after_id' => $afterId,
        'last_id' => $batch['last_id'],
        'hits' => $batch['hits'],
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * @return array{hits:list<array{id:int,ts:int,cc:string,decision:string,cid:string,ua:string,ip:string,lp:string}>, last_id:int}
 */
function fetch_after(\PDO $pdo, int $afterId, int $limit): array
{
    $limit = max(1, min($limit, 500));
    $sql = 'SELECT id, UNIX_TIMESTAMP(created_at) AS ts, ip, ua, cid, cc, lp, decision
            FROM hits WHERE id > :after ORDER BY id ASC LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':after', $afterId, \PDO::PARAM_INT);
    $stmt->execute();

    /** @var list<array{id:int,ts:int,cc:string,decision:string,cid:string,ua:string,ip:string,lp:string}> $rows */
    $rows = [];
    $last = $afterId;
    while ($row = $stmt->fetch()) {
        $id = (int) $row['id'];
        $ts = (int) $row['ts'];
        $cc = substr(strtoupper((string) $row['cc']), 0, 2);
        $dec = ((string) $row['decision']) === 'A' ? 'A' : 'B';
        $cid = substr((string) $row['cid'], 0, 64);
        $ua = substr((string) $row['ua'], 0, 512);
        $ip = substr((string) $row['ip'], 0, 45);
        $lp = substr((string) $row['lp'], 0, 64);
        $rows[] = ['id' => $id, 'ts' => $ts, 'cc' => $cc, 'decision' => $dec, 'cid' => $cid, 'ua' => $ua, 'ip' => $ip, 'lp' => $lp];
        if ($id > $last) {
            $last = $id;
        }
    }

    return ['hits' => $rows, 'last_id' => $last];
}
