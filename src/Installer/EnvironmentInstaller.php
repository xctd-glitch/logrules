<?php

declare(strict_types=1);

namespace SRP\Installer;

use RuntimeException;

final class EnvironmentInstaller
{
    private const TEMPLATE_FILE = '.env.example';
    private const ENV_FILE = '.env';

    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $realPath = realpath($projectRoot);
        if ($realPath === false) {
            throw new RuntimeException('Project root could not be resolved.');
        }

        $this->projectRoot = $realPath;
    }

    public function install(InstallOptions $options): InstallResult
    {
        $templatePath = $this->buildPath(self::TEMPLATE_FILE);
        $envPath = $this->buildPath(self::ENV_FILE);

        $this->assertTemplateExists($templatePath);
        $this->assertWritable($envPath, $options->shouldForceOverwrite());

        [$lines, $apiKeyGenerated] = $this->prepareLines($templatePath, $options->overrides());

        $this->writeFile($envPath, $lines);
        $this->tightenPermissions($envPath);

        return new InstallResult($envPath, $apiKeyGenerated);
    }

    private function buildPath(string $fileName): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $fileName;
    }

    private function assertTemplateExists(string $templatePath): void
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException('Template file .env.example is missing.');
        }
    }

    private function assertWritable(string $envPath, bool $forceOverwrite): void
    {
        if (!file_exists($envPath)) {
            $directory = dirname($envPath);
            if (!is_dir($directory) || !is_writable($directory)) {
                throw new RuntimeException('The target directory is not writable.');
            }

            return;
        }

        if ($forceOverwrite) {
            if (!is_writable($envPath)) {
                throw new RuntimeException('Environment file exists but cannot be overwritten.');
            }

            return;
        }

        throw new RuntimeException('Environment file already exists. Use --force to overwrite.');
    }

    /**
     * @param array<string, string> $overrides
     * @return array{0: list<string>, 1: bool}
     */
    private function prepareLines(string $templatePath, array $overrides): array
    {
        $lines = file($templatePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment template.');
        }

        $apiKeyGenerated = false;
        $prepared = [];
        foreach ($lines as $line) {
            $prepared[] = $this->buildLine($line, $overrides, $apiKeyGenerated);
        }

        return [$prepared, $apiKeyGenerated];
    }

    /**
     * @param array<string, string> $overrides
     */
    private function buildLine(string $line, array $overrides, bool &$apiKeyGenerated): string
    {
        if (!preg_match('~^([A-Z0-9_]+)=~', $line, $matches)) {
            return $line;
        }

        $key = $matches[1];
        $default = (string) substr($line, strlen($matches[0]));

        $value = $overrides[$key] ?? $default;
        $value = $this->normalizeValue($key, $value, $apiKeyGenerated);

        return $key . '=' . $value;
    }

    private function normalizeValue(string $key, string $value, bool &$apiKeyGenerated): string
    {
        if ($key === 'SRP_API_KEY') {
            return $this->normalizeApiKey($value, $apiKeyGenerated);
        }

        if ($key === 'SRP_DB_PASS') {
            return $this->wrapSecret($value);
        }

        return $value;
    }

    private function normalizeApiKey(string $value, bool &$apiKeyGenerated): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'change-me') {
            $apiKeyGenerated = true;

            return $this->wrapSecret($this->generateApiKey());
        }

        return $this->wrapSecret($trimmed);
    }

    private function wrapSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return sprintf('"%s"', addcslashes($value, "\"\\"));
    }

    private function generateApiKey(): string
    {
        $bytes = random_bytes(32);

        $encoded = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        if ($encoded === '') {
            throw new RuntimeException('Failed to generate API key.');
        }

        return $encoded;
    }

    /**
     * @param list<string> $lines
     */
    private function writeFile(string $envPath, array $lines): void
    {
        $contents = implode(PHP_EOL, $lines) . PHP_EOL;
        $tempFile = tempnam(dirname($envPath), 'env');
        if ($tempFile === false) {
            throw new RuntimeException('Unable to create a temporary file for .env.');
        }

        $bytesWritten = file_put_contents($tempFile, $contents);
        if ($bytesWritten === false) {
            throw new RuntimeException('Failed to write environment file.');
        }

        if (!@rename($tempFile, $envPath)) {
            @unlink($tempFile);
            throw new RuntimeException('Failed to move environment file into place.');
        }
    }

    private function tightenPermissions(string $envPath): void
    {
        @chmod($envPath, 0600);
    }
}
