<?php

declare(strict_types=1);

namespace SRP\Installer;

use InvalidArgumentException;

final class InstallOptions
{
    private const OVERRIDE_KEYS = [
        'SRP_DB_HOST',
        'SRP_DB_PORT',
        'SRP_DB_NAME',
        'SRP_DB_USER',
        'SRP_DB_PASS',
        'SRP_API_KEY',
        'SRP_CORS_ALLOW_ORIGIN',
    ];

    private bool $forceOverwrite;

    /**
     * @var array<string, string>
     */
    private array $overrides;

    /**
     * @param array<string, string> $overrides
     */
    public function __construct(bool $forceOverwrite, array $overrides)
    {
        $this->forceOverwrite = $forceOverwrite;
        $this->overrides = $this->filterOverrides($overrides);
    }

    public function shouldForceOverwrite(): bool
    {
        return $this->forceOverwrite;
    }

    /**
     * @return array<string, string>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    /**
     * @param array<string, string> $rawOverrides
     * @return array<string, string>
     */
    private function filterOverrides(array $rawOverrides): array
    {
        $filtered = [];

        foreach (self::OVERRIDE_KEYS as $key) {
            if (!array_key_exists($key, $rawOverrides)) {
                continue;
            }

            $value = trim($rawOverrides[$key]);
            if ($value === '') {
                continue;
            }

            $filtered[$key] = $this->sanitizeValue($key, $value);
        }

        return $filtered;
    }

    private function sanitizeValue(string $key, string $value): string
    {
        if (str_contains($value, "\n")) {
            throw new InvalidArgumentException(sprintf('Value for %s contains a newline which is not allowed.', $key));
        }

        return match ($key) {
            'SRP_DB_PORT' => $this->sanitizePort($value),
            'SRP_DB_HOST' => $this->sanitizeHost($value),
            'SRP_DB_NAME' => $this->sanitizeIdentifier($value, $key),
            'SRP_DB_USER' => $this->sanitizeIdentifier($value, $key),
            'SRP_CORS_ALLOW_ORIGIN' => $this->sanitizeOrigins($value),
            default => $value,
        };
    }

    private function sanitizePort(string $value): string
    {
        $port = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ],
        ]);

        if ($port === false) {
            throw new InvalidArgumentException('Database port must be a valid integer between 1 and 65535.');
        }

        return (string) $port;
    }

    private function sanitizeHost(string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException('Database host may not be empty.');
        }

        if (!preg_match('~^[A-Za-z0-9._-]+$~', $value)) {
            throw new InvalidArgumentException('Database host contains invalid characters.');
        }

        return $value;
    }

    private function sanitizeIdentifier(string $value, string $key): string
    {
        if (!preg_match('~^[A-Za-z0-9_]+$~', $value)) {
            throw new InvalidArgumentException(
                sprintf('%s may only contain alphanumeric characters and underscores.', $key)
            );
        }

        return $value;
    }

    private function sanitizeOrigins(string $value): string
    {
        $parts = array_filter(
            array_map(
                static fn (string $item): string => trim($item),
                explode(',', $value)
            )
        );
        if ($parts === []) {
            return '';
        }

        $validated = [];
        foreach ($parts as $origin) {
            $origin = strtolower($origin);
            $parsed = parse_url($origin);
            if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
                throw new InvalidArgumentException('Each origin must be a valid URL.');
            }

            if (
                isset($parsed['user'])
                || isset($parsed['pass'])
                || isset($parsed['path'])
                || isset($parsed['query'])
                || isset($parsed['fragment'])
            ) {
                throw new InvalidArgumentException(
                    'Origins must not include authentication, path, query, or fragment components.'
                );
            }

            $scheme = $parsed['scheme'];
            if ($scheme !== 'https' && $scheme !== 'http') {
                throw new InvalidArgumentException('Origins must use http or https scheme.');
            }

            $host = $this->sanitizeOriginHost($parsed['host']);
            $port = null;
            if (isset($parsed['port'])) {
                $port = filter_var(
                    $parsed['port'],
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1,
                            'max_range' => 65535,
                        ],
                    ]
                );

                if ($port === false) {
                    throw new InvalidArgumentException('Origin port must be between 1 and 65535.');
                }
            }

            $validated[] = $port === null
                ? sprintf('%s://%s', $scheme, $host)
                : sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return implode(',', array_unique($validated));
    }

    private function sanitizeOriginHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            throw new InvalidArgumentException('Origin host may not be empty.');
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            if (strlen($host) <= 2) {
                throw new InvalidArgumentException('Origin host must be a valid IPv6 address.');
            }

            $ip = substr($host, 1, -1);
            if ($ip === '') {
                throw new InvalidArgumentException('Origin host must be a valid IPv6 address.');
            }

            $normalized = strtolower($ip);
            if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new InvalidArgumentException('Origin host must be a valid IPv6 address.');
            }

            return '[' . $normalized . ']';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
            return $host;
        }

        throw new InvalidArgumentException('Origin host must be a valid hostname or IP address.');
    }
}
