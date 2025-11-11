<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction('SRP\\readLimitedStream')]
final class InputStreamTest extends TestCase
{
    public function testReadLimitedStreamReturnsDataWithinLimit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'srp');
        self::assertIsString($path);

        try {
            $data = 'payload';
            $written = file_put_contents($path, $data);
            self::assertSame(strlen($data), $written);

            [$contents, $overflowed] = \SRP\readLimitedStream($path, 8192);

            self::assertSame($data, $contents);
            self::assertFalse($overflowed);
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testReadLimitedStreamDetectsOverflow(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'srp');
        self::assertIsString($path);

        try {
            $data = str_repeat('a', 8200);
            $written = file_put_contents($path, $data);
            self::assertSame(strlen($data), $written);

            [$contents, $overflowed] = \SRP\readLimitedStream($path, 8192);

            self::assertSame(8192, strlen($contents));
            self::assertTrue($overflowed);
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }
}
