<?php

declare(strict_types=1);

namespace SRP\Tests\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SRP\Installer\EnvironmentInstaller;
use SRP\Installer\InstallOptions;
use SRP\Installer\InstallResult;

#[CoversClass(EnvironmentInstaller::class)]
#[CoversClass(InstallOptions::class)]
#[CoversClass(InstallResult::class)]
final class EnvironmentInstallerTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'srp-install-' . bin2hex(random_bytes(5));
        if (!mkdir($this->fixtureDir) && !is_dir($this->fixtureDir)) {
            $this->fail('Unable to create fixture directory.');
        }

        $template = file_get_contents(__DIR__ . '/../../.env.example');
        if ($template === false) {
            $this->fail('Unable to load template fixture.');
        }

        file_put_contents($this->fixtureDir . '/.env.example', $template);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
        parent::tearDown();
    }

    public function testCreatesEnvAndGeneratesApiKey(): void
    {
        $installer = new EnvironmentInstaller($this->fixtureDir);
        $options = new InstallOptions(false, [
            'SRP_DB_HOST' => 'localhost',
            'SRP_DB_PORT' => '3306',
            'SRP_DB_NAME' => 'smart_redirect',
            'SRP_DB_USER' => 'srp_user',
            'SRP_DB_PASS' => 'superSecret!@#',
        ]);

        $result = $installer->install($options);

        self::assertFileExists($result->envPath());
        $envContent = (string) file_get_contents($result->envPath());
        self::assertStringContainsString('SRP_DB_PASS="superSecret!@#"', $envContent);
        self::assertMatchesRegularExpression('~SRP_API_KEY="[A-Za-z0-9\-_]{42,}"~', $envContent);
        self::assertTrue($result->apiKeyGenerated());
    }

    public function testThrowsWhenEnvExistsWithoutForce(): void
    {
        $envPath = $this->fixtureDir . '/.env';
        file_put_contents($envPath, 'SRP_DB_HOST=localhost');

        $installer = new EnvironmentInstaller($this->fixtureDir);
        $options = new InstallOptions(false, [
            'SRP_DB_HOST' => 'localhost',
            'SRP_DB_NAME' => 'smart_redirect',
            'SRP_DB_USER' => 'srp_user',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment file already exists');

        $installer->install($options);
    }

    public function testForceOverwriteRefreshesFile(): void
    {
        $envPath = $this->fixtureDir . '/.env';
        file_put_contents($envPath, 'SRP_DB_HOST=old');

        $installer = new EnvironmentInstaller($this->fixtureDir);
        $options = new InstallOptions(true, [
            'SRP_DB_HOST' => 'dbserver',
            'SRP_DB_NAME' => 'smart_redirect',
            'SRP_DB_USER' => 'srp_user',
            'SRP_DB_PASS' => 'newSecret',
            'SRP_API_KEY' => 'manual-key',
        ]);

        $result = $installer->install($options);

        $envContent = (string) file_get_contents($result->envPath());
        self::assertStringContainsString('SRP_DB_HOST=dbserver', $envContent);
        self::assertStringContainsString('SRP_API_KEY="manual-key"', $envContent);
        self::assertFalse($result->apiKeyGenerated());
    }

    private function removeDirectory(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }
}
