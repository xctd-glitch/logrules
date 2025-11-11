<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SRP\Validation\CountryCodeValidator;

/**
 * Read environment value, trimmed.
 */
function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    $value = trim((string) $value);

    return $value === '' ? $default : $value;
}

function cleanIdentifier(string $value, int $maxLength = 64): string
{
    $filtered = preg_replace('~[^A-Za-z0-9_-]~', '', $value) ?? '';

    return strtoupper(substr($filtered, 0, $maxLength));
}

function cleanCountryCode(string $value): string
{
    return CountryCodeValidator::ensureOrFallback($value);
}

function resolveIpAddress(): string
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
            if ($ip === '') {
                continue;
            }

            $validated = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($validated !== false) {
                return $validated;
            }
        }
    }

    $fallback = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return is_string($fallback) ? $fallback : '0.0.0.0';
}

function fallbackRedirect(string $reason, string $baseUrl): void
{
    $target = $baseUrl !== '' ? $baseUrl : '/';
    $separator = str_contains($target, '?') ? '&' : '?';
    $location = $target . $separator . 'reason=' . rawurlencode($reason);

    header('Location: ' . $location, true, 302);
    exit;
}

$apiUrl = env('SRP_DECISION_URL');
$apiKey = env('SRP_DECISION_KEY');
$fallbackBase = env('SRP_FALLBACK_URL', '/');
if ($fallbackBase !== '' && !str_starts_with($fallbackBase, '/') && !filter_var($fallbackBase, FILTER_VALIDATE_URL)) {
    $fallbackBase = '/';
}

if ($apiUrl === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
    http_response_code(500);
    exit('Decision endpoint misconfigured.');
}

if ($apiKey === '') {
    http_response_code(500);
    exit('Decision API key missing.');
}

$clickId = cleanIdentifier((string) ($_GET['click_id'] ?? ''));
if ($clickId === '') {
    $clickId = 'ANON';
}

$countryCode = cleanCountryCode((string) ($_GET['country_code'] ?? ''));
$userLp = cleanIdentifier((string) ($_GET['user_lp'] ?? ''), 64);
if ($userLp === '') {
    $userLp = 'DEFAULT';
}

$userAgent = mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 512);
if ($userAgent === '') {
    $userAgent = 'unknown';
}

$ipAddress = resolveIpAddress();

$postData = [
    'click_id' => $clickId,
    'country_code' => $countryCode,
    'user_agent' => $userAgent,
    'ip_address' => $ipAddress,
    'user_lp' => $userLp,
];

try {
    $payload = json_encode($postData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    fallbackRedirect('encode_error', $fallbackBase);
}

$handle = curl_init($apiUrl);
if ($handle === false) {
    fallbackRedirect('curl_init', $fallbackBase);
}

curl_setopt_array($handle, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_WHATEVER,
]);

$responseBody = curl_exec($handle);
$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
$errorNo = curl_errno($handle);
curl_close($handle);

if (!is_string($responseBody) || $errorNo !== 0 || $status !== 200) {
    fallbackRedirect('upstream_error', $fallbackBase);
}

try {
    $decoded = json_decode($responseBody, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fallbackRedirect('bad_response', $fallbackBase);
}

if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
    fallbackRedirect('decision_rejected', $fallbackBase);
}

$target = (string) ($decoded['target'] ?? '');
if ($target === '') {
    fallbackRedirect('empty_target', $fallbackBase);
}

if (!str_starts_with($target, '/') && !filter_var($target, FILTER_VALIDATE_URL)) {
    fallbackRedirect('invalid_target', $fallbackBase);
}

header('Location: ' . $target, true, 302);
exit;
