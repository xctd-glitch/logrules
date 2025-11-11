<?php

declare(strict_types=1);

namespace SRP;

use CurlHandle;
use PDO;
use PDOException;
use SRP\Validation\CountryCodeValidator;
use Throwable;

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    $value = is_string($value) ? trim($value) : '';

    return $value !== '' ? $value : $default;
}

function db(): PDO
{
    /** @var null|PDO $pdo */
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('SRP_DB_HOST');
    $name = env('SRP_DB_NAME');
    $user = env('SRP_DB_USER');
    $pass = env('SRP_DB_PASS');
    $port = (int) env('SRP_DB_PORT', '3306');
    if ($port <= 0 || $port > 65535) {
        $port = 3306;
    }

    if ($host === '' || $name === '' || $user === '') {
        http_response_code(500);
        throw new RuntimeException('Database credentials are incomplete.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET SESSION time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        bootstrapSchema($pdo);
    } catch (PDOException $e) {
        http_response_code(500);
        throw new RuntimeException('Database connection failed.', 0, $e);
    }

    return $pdo;
}

function bootstrapSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            redirect_url VARCHAR(2048) NOT NULL DEFAULT '',
            system_on TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            allowed_countries JSON NULL,
            rule_mode VARCHAR(32) NOT NULL DEFAULT 'none',
            rule_started_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            ua VARCHAR(512) NOT NULL,
            cid VARCHAR(64) NOT NULL,
            cc CHAR(2) NOT NULL,
            lp VARCHAR(64) NOT NULL,
            decision ENUM('A','B') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created_at (created_at),
            KEY idx_cc (cc),
            KEY idx_decision (decision)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    foreach ([
        'is_active' => static function (PDO $pdo): void {
            $pdo->exec("ALTER TABLE settings ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER system_on");
            $pdo->exec("UPDATE settings SET is_active = 0 WHERE id = 1");
        },
        'allowed_countries' => static function (PDO $pdo): void {
            $pdo->exec("ALTER TABLE settings ADD COLUMN allowed_countries JSON NULL AFTER is_active");
            $pdo->exec("UPDATE settings SET allowed_countries = JSON_ARRAY() WHERE id = 1 AND allowed_countries IS NULL");
        },
        'rule_mode' => static function (PDO $pdo): void {
            $pdo->exec("ALTER TABLE settings ADD COLUMN rule_mode VARCHAR(32) NOT NULL DEFAULT 'none' AFTER allowed_countries");
            $pdo->exec("UPDATE settings SET rule_mode = 'none' WHERE id = 1");
        },
        'rule_started_at' => static function (PDO $pdo): void {
            $pdo->exec("ALTER TABLE settings ADD COLUMN rule_started_at TIMESTAMP NULL DEFAULT NULL AFTER rule_mode");
            $pdo->exec("UPDATE settings SET rule_started_at = NULL WHERE id = 1");
        },
    ] as $column => $migration) {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'settings'
                 AND COLUMN_NAME = :column"
        );
        $stmt->bindValue(':column', $column, PDO::PARAM_STR);
        $stmt->execute();
        $hasColumn = $stmt->fetchColumn() !== false;

        if ($hasColumn === false) {
            $migration($pdo);
        }
    }
    $stmt = $pdo->prepare(
        "INSERT INTO settings (id, redirect_url, system_on, is_active, allowed_countries) VALUES (1, '', 0, 0, JSON_ARRAY()) "
        . "ON DUPLICATE KEY UPDATE id = VALUES(id)"
    );
    $stmt->execute();

}

function assertRedirectUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (strlen($url) > 2048) {
        return '';
    }

    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = @parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if ($scheme !== 'https' && $scheme !== 'http') {
        return '';
    }

    $host = strtolower((string) $parts['host']);
    if (strlen($host) > 253 || strlen($host) < 1) {
        return '';
    }

    if (!preg_match('~^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$~i', $host)) {
        return '';
    }

    return rtrim($url, '/');
}

function normalizeRuleMode(?string $mode): string
{
    $allowed = ['none', 'mute_cycle', 'random_route', 'static_route'];
    $normalized = strtolower(trim((string) $mode));

    if ($normalized === '' || $normalized === 'normal') {
        return 'none';
    }

    return in_array($normalized, $allowed, true) ? $normalized : 'none';
}

/**
 * @return array{
 *     system_on: bool,
 *     redirect_url: string,
 *     is_active: bool,
 *     allowed_countries: array<int, string>,
 *     rule_mode: string,
 *     rule_started_at: null|int,
 *     updated_at: int
 * }
 */
function cfg(): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT redirect_url, system_on, is_active, rule_mode, COALESCE(allowed_countries, JSON_ARRAY()),
                UNIX_TIMESTAMP(updated_at) AS updated_at,
                UNIX_TIMESTAMP(rule_started_at) AS rule_started_at
         FROM settings WHERE id = 1'
    );
    $stmt->execute();

    $row = $stmt->fetch();
    if ($row === false) {
        return [
            'system_on' => false,
            'redirect_url' => '',
            'is_active' => false,
            'allowed_countries' => [],
            'rule_mode' => 'none',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];
    }

    try {
        $decoded = json_decode((string) ($row['allowed_countries'] ?? '[]'), true, 16, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        $decoded = [];
    }
    $countries = is_array($decoded)
        ? CountryCodeValidator::sanitizeList($decoded)
        : [];

    return [
        'system_on' => (bool) $row['system_on'],
        'redirect_url' => is_string($row['redirect_url']) ? $row['redirect_url'] : '',
        'is_active' => (bool) ($row['is_active'] ?? false),
        'allowed_countries' => $countries,
        'rule_mode' => normalizeRuleMode(isset($row['rule_mode']) ? (string) $row['rule_mode'] : 'none'),
        'rule_started_at' => isset($row['rule_started_at']) && $row['rule_started_at'] !== null
            ? (int) $row['rule_started_at']
            : null,
        'updated_at' => isset($row['updated_at']) ? (int) $row['updated_at'] : 0,
    ];
}

/**
 * @param array<string, mixed> $partial
 * @return array{
 *     system_on: bool,
 *     redirect_url: string,
 *     is_active: bool,
 *     allowed_countries: array<int, string>,
 *     rule_mode: string,
 *     rule_started_at: null|int,
 *     updated_at: int
 * }
 */
function cfgUpdate(array $partial): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $current = cfg();
        $systemOn = array_key_exists('system_on', $partial)
            ? (bool) $partial['system_on']
            : $current['system_on'];

        $currentIsActive = (bool) ($current['is_active'] ?? false);
        $isActive = array_key_exists('is_active', $partial)
            ? (bool) $partial['is_active']
            : $currentIsActive;

        $redirect = $current['redirect_url'];
        if (array_key_exists('redirect_url', $partial) && is_string($partial['redirect_url'])) {
            $redirect = assertRedirectUrl($partial['redirect_url']);
        }

        $countries = $current['allowed_countries'];
        if (array_key_exists('allowed_countries', $partial)) {
            $source = $partial['allowed_countries'];
            if (is_string($source) || is_array($source)) {
                $countries = CountryCodeValidator::sanitizeList($source);
            }
        }

        $currentRuleMode = normalizeRuleMode($current['rule_mode'] ?? 'none');
        $ruleMode = array_key_exists('rule_mode', $partial) && is_string($partial['rule_mode'])
            ? normalizeRuleMode($partial['rule_mode'])
            : $currentRuleMode;

        $ruleStartedAt = $current['rule_started_at'] ?? null;
        $shouldResetRuleTimer = $ruleMode !== $currentRuleMode || $isActive !== $currentIsActive;

        if (!$isActive || $ruleMode === 'none') {
            $ruleStartedAt = null;
        } elseif ($shouldResetRuleTimer || !is_int($ruleStartedAt)) {
            $ruleStartedAt = time();
        } else {
            $ruleStartedAt = max(0, $ruleStartedAt);
        }

        $encodedCountries = json_encode($countries, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $stmt = $pdo->prepare(
            "INSERT INTO settings (id, redirect_url, system_on, is_active, allowed_countries, rule_mode, rule_started_at, updated_at)
             VALUES (1, :redirect, :system_on, :is_active, :countries, :rule_mode, :rule_started_at, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE redirect_url = VALUES(redirect_url),
                                     system_on = VALUES(system_on),
                                     is_active = VALUES(is_active),
                                     allowed_countries = VALUES(allowed_countries),
                                     rule_mode = VALUES(rule_mode),
                                     rule_started_at = VALUES(rule_started_at),
                                     updated_at = VALUES(updated_at)"
        );
        $stmt->bindValue(':redirect', $redirect, PDO::PARAM_STR);
        $stmt->bindValue(':system_on', $systemOn ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':countries', $encodedCountries, PDO::PARAM_STR);
        $stmt->bindValue(':rule_mode', $ruleMode, PDO::PARAM_STR);
        if ($ruleStartedAt === null) {
            $stmt->bindValue(':rule_started_at', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':rule_started_at', gmdate('Y-m-d H:i:s', $ruleStartedAt), PDO::PARAM_STR);
        }
        $stmt->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return cfg();
}

function detectDevice(string $userAgent): string
{
    $ua = mb_substr(trim($userAgent), 0, 512);
    if ($ua === '') {
        return 'WEB';
    }

    if (preg_match('~\b(bot|crawl|spider)\b~i', $ua)) {
        return 'BOT';
    }

    if (class_exists(\Detection\MobileDetect::class)) {
        $detector = new \Detection\MobileDetect();
        if (method_exists($detector, 'setUserAgent')) {
            $detector->setUserAgent($ua);
        }

        if ($detector->isTablet()) {
            return 'TABLET';
        }

        if ($detector->isMobile()) {
            return 'WAP';
        }

        return 'WEB';
    }

    if (preg_match('~\b(tablet|ipad)\b~i', $ua)) {
        return 'TABLET';
    }

    if (preg_match('~\b(iphone|android|mobile|mobi|wap)\b~i', $ua)) {
        return 'WAP';
    }

    return 'WEB';
}

function clientIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_TRUE_CLIENT_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $raw) {
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }

        foreach (explode(',', $raw) as $part) {
            $ip = trim($part);
            if ($ip === '' || strlen($ip) > 45) {
                continue;
            }

            $validated = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($validated !== false) {
                return $validated;
            }
        }
    }

    $fallback = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!is_string($fallback)) {
        return '0.0.0.0';
    }

    $validated = filter_var($fallback, FILTER_VALIDATE_IP);
    return $validated !== false ? $validated : '0.0.0.0';
}

function httpGet(string $url, int $timeoutMs = 1500, int $connectTimeoutMs = 750): ?string
{
    $handle = curl_init();
    if (!($handle instanceof CurlHandle)) {
        return null;
    }

    curl_setopt_array($handle, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'SRP/1.0 (+vpn-check)',
        CURLOPT_HTTPHEADER => ['Accept: text/plain'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_WHATEVER,
    ]);

    $body = curl_exec($handle);
    $error = curl_errno($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($error !== 0 || $status !== 200 || !is_string($body)) {
        return null;
    }

    return $body;
}

/**
 * @return array{0: string, 1: bool}
 */
function readLimitedStream(string $source, int $limit): array
{
    $limit = max(0, $limit);

    $handle = @fopen($source, 'rb');
    if ($handle === false) {
        return ['', false];
    }

    try {
        $data = stream_get_contents($handle, $limit + 1);
        if ($data === false) {
            return ['', false];
        }
    } finally {
        @fclose($handle);
    }

    if (strlen($data) > $limit) {
        return [substr($data, 0, $limit), true];
    }

    return [$data, false];
}

function cacheGet(string $namespace, string $key, mixed &$out): bool
{
    $cacheKey = $namespace . ':' . sha1($key);
    if (function_exists('apcu_fetch')) {
        $out = apcu_fetch($cacheKey, $hit);
        if ($hit) {
            return true;
        }
    }

    $dir = sys_get_temp_dir() . '/srp_cache';
    $path = $dir . '/' . $cacheKey . '.json';
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if (is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['exp']) && time() < (int) $decoded['exp']) {
                    $out = $decoded['val'] ?? null;
                    return true;
                }
            } catch (\JsonException $e) {
                @unlink($path);
            }
        }
    }

    return false;
}

function cacheSet(string $namespace, string $key, mixed $value, int $ttl): void
{
    $cacheKey = $namespace . ':' . sha1($key);
    $ttl = max(1, min($ttl, 3600));

    if (function_exists('apcu_store')) {
        @apcu_store($cacheKey, $value, $ttl);
    }

    $dir = sys_get_temp_dir() . '/srp_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $path = $dir . '/' . $cacheKey . '.json';
    $payload = ['exp' => time() + $ttl, 'val' => $value];
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function vpnLookup(string $ip): bool
{
    $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($valid === false) {
        return true;
    }

    $namespace = 'vpn';
    $key = $ip;
    if (cacheGet($namespace, $key, $cached)) {
        return (bool) $cached;
    }

    $url = 'https://blackbox.ipinfo.app/lookup/' . rawurlencode($ip);
    $isVpn = true;
    $body = httpGet($url, 1500, 750);
    if (is_string($body)) {
        $isVpn = trim($body) === 'Y';
    }

    cacheSet($namespace, $key, $isVpn, 60);

    return $isVpn;
}

/**
 * @param array<string, mixed> $payload
 * @param null|callable(string):bool $vpnResolver
 * @param null|callable():array{
 *     system_on: bool,
 *     redirect_url: string,
 *     allowed_countries: array<int, string>,
 *     is_active: bool,
 *     rule_mode: string,
 *     rule_started_at: null|int,
 *     updated_at: int
 * } $configResolver
 * @param null|callable():bool $randomizer
 * @param null|callable():int $clock
 * @return array{
 *     response: array{
 *         decision: string,
 *         target: string,
 *         meta: array{device: string, vpn: bool, client_ip: string, country_code: string}
 *     },
 *     log: array{ip: string, ua: string, cid: string, cc: string, lp: string, decision: string}
 * }
 */
function decisionResponse(
    array $payload,
    ?callable $vpnResolver = null,
    ?callable $configResolver = null,
    ?callable $randomizer = null,
    ?callable $clock = null
): array
{
    $cid = strtoupper(substr(preg_replace('~[^A-Za-z0-9_-]~', '', (string) ($payload['click_id'] ?? '')), 0, 64));
    $cid = $cid !== '' ? $cid : 'ANON';
    $ccRaw = (string) ($payload['country_code'] ?? 'XX');
    $cc = CountryCodeValidator::ensureOrFallback($ccRaw);
    $uaRaw = trim((string) ($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $ua = mb_substr($uaRaw, 0, 512);
    $lp = strtoupper(substr(preg_replace('~[^A-Za-z0-9_-]~', '', (string) ($payload['user_lp'] ?? '')), 0, 64));
    $lp = $lp !== '' ? $lp : 'DEFAULT';
    $ipInput = trim((string) ($payload['ip_address'] ?? ''));
    if ($ipInput !== '') {
        $validatedIp = filter_var($ipInput, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $ipInput = $validatedIp !== false ? $validatedIp : '';
    }

    $device = detectDevice($ua);
    $ipClient = clientIp();

    $vpnCallback = $vpnResolver ?? static fn (string $ip): bool => vpnLookup($ip);
    $configCallback = $configResolver ?? static fn (): array => cfg();
    $randomCallback = $randomizer ?? static fn (): bool => random_int(0, 1) === 1;
    $clockCallback = $clock ?? static fn (): int => time();
    $now = (int) $clockCallback();

    $fallback = '/_meetups/?' . http_build_query([
        'click_id' => strtolower($cid),
        'country_code' => strtolower($cc),
        'user_agent' => strtolower($device),
        'ip_address' => $ipInput !== '' ? $ipInput : $ipClient,
        'user_lp' => strtolower($lp),
    ], '', '&', PHP_QUERY_RFC3986);

    $config = $configCallback();
    $target = $fallback;
    $decision = 'B';
    $allowOk = $config['allowed_countries'] === [] || in_array($cc, $config['allowed_countries'], true);
    $isActive = (bool) ($config['is_active'] ?? false);
    $ruleMode = normalizeRuleMode($config['rule_mode'] ?? 'none');
    $ruleStartedAt = isset($config['rule_started_at']) && $config['rule_started_at'] !== null
        ? max(0, (int) $config['rule_started_at'])
        : null;

    $baseEligible = (
        $config['system_on']
        && $config['redirect_url'] !== ''
        && $allowOk
        && $device === 'WAP'
    );

    $ruleAllowsRedirect = false;
    if ($baseEligible && $isActive) {
        $ruleAllowsRedirect = true;
        if ($ruleMode !== 'none') {
            switch ($ruleMode) {
                case 'mute_cycle':
                    $reference = $ruleStartedAt ?? (int) ($config['updated_at'] ?? $now);
                    $elapsed = max(0, $now - $reference);
                    $phase = $elapsed % 240;
                    $ruleAllowsRedirect = $phase < 120;
                    break;
                case 'random_route':
                    $ruleAllowsRedirect = (bool) $randomCallback();
                    break;
                case 'static_route':
                    $ruleAllowsRedirect = true;
                    break;
                default:
                    $ruleAllowsRedirect = true;
            }
        }
    }

    $shouldAttemptRedirect = $baseEligible && $isActive && $ruleAllowsRedirect;

    $vpn = false;

    if ($shouldAttemptRedirect) {
        $targetCandidate = rtrim($config['redirect_url'], '/');
        if ($ruleMode === 'static_route') {
            $decision = 'A';
            $target = $targetCandidate;
        } else {
            $vpn = (bool) $vpnCallback($ipClient);
            if (!$vpn) {
                $decision = 'A';
                $target = $targetCandidate;
            }
        }
    }

    return [
        'response' => [
            'decision' => $decision,
            'target' => $target,
            'meta' => [
                'device' => $device,
                'vpn' => $vpn,
                'client_ip' => $ipClient,
                'country_code' => $cc,
            ],
        ],
        'log' => [
            'ip' => $ipClient,
            'ua' => $ua,
            'cid' => $cid,
            'cc' => $cc,
            'lp' => $lp,
            'decision' => $decision,
        ],
    ];
}

/**
 * @param array{ip: string, ua: string, cid: string, cc: string, lp: string, decision: string} $row
 */
function logHit(array $row): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO hits (ip, ua, cid, cc, lp, decision) VALUES (:ip, :ua, :cid, :cc, :lp, :decision)');
        $stmt->bindValue(':ip', substr($row['ip'], 0, 45), PDO::PARAM_STR);
        $stmt->bindValue(':ua', substr($row['ua'], 0, 512), PDO::PARAM_STR);
        $stmt->bindValue(':cid', substr($row['cid'], 0, 64), PDO::PARAM_STR);
        $stmt->bindValue(':cc', substr(strtoupper($row['cc']), 0, 2), PDO::PARAM_STR);
        $stmt->bindValue(':lp', substr($row['lp'], 0, 64), PDO::PARAM_STR);
        $stmt->bindValue(':decision', $row['decision'] === 'A' ? 'A' : 'B', PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        // Swallow logging errors to avoid breaking main response path.
    }
}

class RuntimeException extends \RuntimeException
{
}
