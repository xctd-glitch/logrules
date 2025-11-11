<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/_bootstrap.php';

use function SRP\env;
use function SRP\db;
use function SRP\detectDevice;

// ---------- Security headers ----------
header('Content-Type: application/json; charset=utf-8'); 
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ---------- CORS (allow-list) ----------
$origin   = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowEnv = trim((string) env('SRP_CORS_ALLOW_ORIGIN', ''));
if ($origin !== '' && $allowEnv !== '') {
    $allows = preg_split('~\s*,\s*~', $allowEnv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($allows as $o) {
        if ($o === '*' || strcasecmp($origin, $o) === 0) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Max-Age: 600');
            break;
        }
    }
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET') { http_response_code(405); echo '{"ok":false,"error":"method_not_allowed"}'; exit; }

// ---------- Auth ----------
$apiKey = env('SRP_API_KEY', '');
if ($apiKey === '') { http_response_code(500); echo '{"ok":false,"error":"server_misconfigured"}'; exit; }
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) { http_response_code(401); echo '{"ok":false,"error":"unauthorized"}'; exit; }

// ---------- Inputs ----------
$afterId = (int) ($_GET['after_id'] ?? 0);
if ($afterId < 0) { $afterId = 0; }
$timeout = (int) ($_GET['timeout'] ?? 20); // seconds
if ($timeout < 1) { $timeout = 1; }
if ($timeout > 25) { $timeout = 25; }
$limit   = 200; // batch size

$pdo = db();

/**
 * @return array{hits: list<array{id:int,ts:int,cc:string,decision:string,cid:string,ua:string,ip:string,lp:string,device:string}>, last_id:int}
 */

function fetchHitsAfter(\PDO $pdo, int $afterId, int $limit): array
{
    $limit = max(1, min($limit, 500));
    $sql = 'SELECT id, UNIX_TIMESTAMP(created_at) AS ts, ip, ua, cid, cc, lp, decision
            FROM hits WHERE id > :after ORDER BY id ASC LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':after', $afterId, \PDO::PARAM_INT);
    $stmt->execute();

    /** @var list<array{id:int,ts:int,cc:string,decision:string,cid:string,ua:string,ip:string,lp:string,device:string}> $rows */
    $rows = [];
    $last = $afterId;

    while ($row = $stmt->fetch()) {
        $id = (int) $row['id'];
        $ts = (int) $row['ts'];
        $cc = substr(strtoupper((string) $row['cc']), 0, 2);
        $decision = ((string) $row['decision']) === 'A' ? 'A' : 'B';
        $cid = substr((string) $row['cid'], 0, 64);
        $ua = substr((string) $row['ua'], 0, 512);
        $ip = substr((string) $row['ip'], 0, 45);
        $lp = substr((string) $row['lp'], 0, 64);
        $device = detectDevice($ua);

        $rows[] = [
            'id' => $id,
            'ts' => $ts,
            'cc' => $cc,
            'decision' => $decision,
            'cid' => $cid,
            'ua' => $ua,
            'ip' => $ip,
            'lp' => $lp,
            'device' => $device,
        ];

        if ($id > $last) {
            $last = $id;
        }
    }

    return ['hits' => $rows, 'last_id' => $last];
}

/**
 * @return array{window:int,total:int,a:int,b:int,top_countries:list<array{cc:string,c:int}>,series:list<array{ts:int,c:int}>}
 */
function readStats(\PDO $pdo): array
{
    $window = 15; // minutes

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

    $sql = "SELECT FLOOR(UNIX_TIMESTAMP(created_at)/60)*60 AS ts, COUNT(*) AS c
              FROM hits WHERE created_at >= FROM_UNIXTIME(:threshold)
              GROUP BY ts ORDER BY ts ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':threshold', $threshold, \PDO::PARAM_INT);
    $stmt->execute();
    /** @var list<array{ts:int,c:int}> $series */
    $series = [];
    while ($r = $stmt->fetch()) {
        $series[] = [ 'ts' => (int) $r['ts'], 'c' => (int) $r['c'] ];
    }

    return [
        'window' => $window,
        'total' => $total,
        'a' => $a,
        'b' => $b,
        'top_countries' => $top,
        'series' => $series,
        'generated_at' => time(),
    ];
}

// ---------- Long-poll loop ----------
$deadline = time() + $timeout;
$batch = fetchHitsAfter($pdo, $afterId, $limit);
if (count($batch['hits']) === 0) {
    // wait for new rows up to timeout
    while (time() < $deadline) {
        usleep(250000); // 250ms
        $batch = fetchHitsAfter($pdo, $afterId, $limit);
        if (count($batch['hits']) > 0) { break; }
    }
}

$stats = readStats($pdo);

echo json_encode([
    'ok' => true,
    'server_time' => time(),
    'after_id' => $afterId,
    'last_id' => $batch['last_id'],
    'hits' => $batch['hits'],
    'stats' => $stats,
], JSON_UNESCAPED_SLASHES);
