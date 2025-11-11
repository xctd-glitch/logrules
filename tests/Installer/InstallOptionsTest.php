<?php

declare(strict_types=1);

namespace SRP\Tests\Installer;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SRP\Installer\InstallOptions;

#[CoversClass(InstallOptions::class)]
final class InstallOptionsTest extends TestCase
{
    public function testSanitizeOriginsNormalizesValidOrigins(): void
    {
        $options = new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'https://Example.com, http://LOCALHOST:8080, '
                . 'https://[2001:DB8::1]:8443, https://example.com',
        ]);

        self::assertSame(
            [
                'SRP_CORS_ALLOW_ORIGIN' => 'https://example.com,http://localhost:8080,https://[2001:db8::1]:8443',
            ],
            $options->overrides()
        );
    }

    public function testSanitizeOriginsRejectsOriginsWithInvalidHostCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Origin host must be a valid hostname or IP address.');

        new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'https://bad host.com',
        ]);
    }

    public function testSanitizeOriginsRejectsOriginsWithPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Origins must not include authentication, path, query, or fragment components.');

        new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'https://example.com/path',
        ]);
    }

    public function testSanitizeOriginsRejectsUnsupportedSchemes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Origins must use http or https scheme.');

        new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'ftp://example.com',
        ]);
    }

    public function testSanitizeOriginsRejectsPortsOutsideValidRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Origin port must be between 1 and 65535.');

        new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'https://example.com:0',
        ]);
    }

    public function testSanitizeOriginsRejectsOriginsWithCredentials(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Origins must not include authentication, path, query, or fragment components.');

        new InstallOptions(false, [
            'SRP_CORS_ALLOW_ORIGIN' => 'https://user:pass@example.com',
        ]);
    }
}
