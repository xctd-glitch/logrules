<?php

declare(strict_types=1);

namespace SRP\Installer;

final class InstallResult
{
    private string $envPath;
    private bool $apiKeyGenerated;

    public function __construct(string $envPath, bool $apiKeyGenerated)
    {
        $this->envPath = $envPath;
        $this->apiKeyGenerated = $apiKeyGenerated;
    }

    public function envPath(): string
    {
        return $this->envPath;
    }

    public function apiKeyGenerated(): bool
    {
        return $this->apiKeyGenerated;
    }
}
