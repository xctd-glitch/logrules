<?php

declare(strict_types=1);

namespace SRP\Tests\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SRP\Validation\CountryCodeValidator;

#[CoversClass(CountryCodeValidator::class)]
final class CountryCodeValidatorTest extends TestCase
{
    public function testEnsureOrFallbackReturnsFallbackForInvalidCode(): void
    {
        self::assertSame('XX', CountryCodeValidator::ensureOrFallback('invalid'));
    }

    public function testEnsureOrFallbackNormalizesValidCode(): void
    {
        self::assertSame('ID', CountryCodeValidator::ensureOrFallback('id'));
    }

    public function testEnsureOrFallbackNormalizesFallbackCode(): void
    {
        self::assertSame('US', CountryCodeValidator::ensureOrFallback('??', 'us'));
    }

    public function testEnsureOrFallbackReturnsSentinelWhenFallbackInvalid(): void
    {
        self::assertSame('XX', CountryCodeValidator::ensureOrFallback('??', '??'));
    }

    public function testSanitizeListFromCommaSeparatedString(): void
    {
        $result = CountryCodeValidator::sanitizeList('us, ca, mx');

        self::assertSame(['US', 'CA', 'MX'], $result);
    }

    public function testSanitizeListIgnoresInvalidEntries(): void
    {
        $result = CountryCodeValidator::sanitizeList(['US', '??', 'ZZ', 'ca']);

        self::assertSame(['US', 'CA'], $result);
    }

    public function testSanitizeListCoalescesNewLines(): void
    {
        $result = CountryCodeValidator::sanitizeList("us\nca\nmx");

        self::assertSame(['US', 'CA', 'MX'], $result);
    }
}
