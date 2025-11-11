<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;

final class DecisionResponseTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $originalServer = [];

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../_bootstrap.php';

        $this->originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '93.184.216.34';
        unset(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_TRUE_CLIENT_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    public function testVpnLookupSkippedWhenRedirectDisabled(): void
    {
        $vpnCalled = false;
        $vpnResolver = static function (string $ip) use (&$vpnCalled): bool {
            $vpnCalled = true;

            return false;
        };

        $configResolver = static fn (): array => [
            'system_on' => false,
            'redirect_url' => 'https://example.com',
            'is_active' => true,
            'allowed_countries' => [],
            'rule_mode' => 'none',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];

        $result = \SRP\decisionResponse([], $vpnResolver, $configResolver);

        self::assertFalse($vpnCalled);
        self::assertSame('B', $result['response']['decision']);
        self::assertSame(
            '/_meetups/?click_id=anon&country_code=xx&user_agent=web&ip_address=93.184.216.34&user_lp=default',
            $result['response']['target']
        );
    }

    public function testVpnLookupInvokedWhenRedirectEligible(): void
    {
        $calls = 0;
        $vpnResolver = static function (string $ip) use (&$calls): bool {
            $calls++;

            return false;
        };

        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com/path/',
            'is_active' => true,
            'allowed_countries' => ['ID'],
            'rule_mode' => 'none',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];

        $payload = [
            'country_code' => 'id',
            'user_agent' => 'Mozilla/5.0 (Android)',
            'click_id' => 'abc123',
            'user_lp' => 'landing',
        ];

        $result = \SRP\decisionResponse($payload, $vpnResolver, $configResolver);

        self::assertSame(1, $calls);
        self::assertSame('A', $result['response']['decision']);
        self::assertSame('https://example.com/path', $result['response']['target']);
        self::assertFalse($result['response']['meta']['vpn']);
    }

    public function testVpnLookupBlocksWhenResolverReportsVpn(): void
    {
        $calls = 0;
        $vpnResolver = static function (string $ip) use (&$calls): bool {
            $calls++;

            return true;
        };

        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com',
            'is_active' => true,
            'allowed_countries' => [],
            'rule_mode' => 'none',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];

        $payload = [
            'country_code' => 'us',
            'user_agent' => 'Mozilla/5.0 (Android)',
        ];

        $result = \SRP\decisionResponse($payload, $vpnResolver, $configResolver);

        self::assertSame(1, $calls);
        self::assertSame('B', $result['response']['decision']);
        self::assertTrue($result['response']['meta']['vpn']);
    }

    public function testRedirectSkipsWhenCampaignInactive(): void
    {
        $vpnCalled = false;
        $vpnResolver = static function (string $ip) use (&$vpnCalled): bool {
            $vpnCalled = true;

            return false;
        };

        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com/path/',
            'is_active' => false,
            'allowed_countries' => ['ID'],
            'rule_mode' => 'none',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];

        $payload = [
            'country_code' => 'id',
            'user_agent' => 'Mozilla/5.0 (Android)',
            'click_id' => 'abc123',
            'user_lp' => 'landing',
        ];

        $result = \SRP\decisionResponse($payload, $vpnResolver, $configResolver);

        self::assertFalse($vpnCalled);
        self::assertSame('B', $result['response']['decision']);
        self::assertSame(
            '/_meetups/?click_id=abc123&country_code=id&user_agent=wap&ip_address=93.184.216.34&user_lp=landing',
            $result['response']['target']
        );
    }

    public function testMuteCycleAlternatesBasedOnTimer(): void
    {
        $vpnCalls = 0;
        $vpnResolver = static function (string $ip) use (&$vpnCalls): bool {
            $vpnCalls++;

            return false;
        };

        $start = 1_700_000_000;
        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com',
            'is_active' => true,
            'allowed_countries' => [],
            'rule_mode' => 'mute_cycle',
            'rule_started_at' => 1_700_000_000,
            'updated_at' => 1_700_000_000,
        ];

        $payload = [
            'country_code' => 'us',
            'user_agent' => 'Mozilla/5.0 (Android)',
        ];

        $active = \SRP\decisionResponse(
            $payload,
            $vpnResolver,
            $configResolver,
            null,
            static fn (): int => $start + 60
        );

        self::assertSame(1, $vpnCalls);
        self::assertSame('A', $active['response']['decision']);

        $vpnCalls = 0;
        $muted = \SRP\decisionResponse(
            $payload,
            $vpnResolver,
            $configResolver,
            null,
            static fn (): int => $start + 180
        );

        self::assertSame(0, $vpnCalls);
        self::assertSame('B', $muted['response']['decision']);
    }

    public function testRandomRouteHonorsRandomizer(): void
    {
        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com',
            'is_active' => true,
            'allowed_countries' => [],
            'rule_mode' => 'random_route',
            'rule_started_at' => null,
            'updated_at' => 1_700_000_000,
        ];

        $payload = [
            'country_code' => 'us',
            'user_agent' => 'Mozilla/5.0 (Android)',
        ];

        $vpnCalled = false;
        $vpnResolver = static function (string $ip) use (&$vpnCalled): bool {
            $vpnCalled = true;

            return false;
        };

        $resultAllow = \SRP\decisionResponse(
            $payload,
            $vpnResolver,
            $configResolver,
            static fn (): bool => true,
            static fn (): int => 1_700_000_100
        );

        self::assertTrue($vpnCalled);
        self::assertSame('A', $resultAllow['response']['decision']);

        $vpnCalled = false;
        $resultDeny = \SRP\decisionResponse(
            $payload,
            $vpnResolver,
            $configResolver,
            static fn (): bool => false,
            static fn (): int => 1_700_000_120
        );

        self::assertFalse($vpnCalled);
        self::assertSame('B', $resultDeny['response']['decision']);
    }

    public function testStaticRouteBypassesVpn(): void
    {
        $vpnCalled = false;
        $vpnResolver = static function (string $ip) use (&$vpnCalled): bool {
            $vpnCalled = true;

            return true;
        };

        $configResolver = static fn (): array => [
            'system_on' => true,
            'redirect_url' => 'https://example.com/target',
            'is_active' => true,
            'allowed_countries' => [],
            'rule_mode' => 'static_route',
            'rule_started_at' => 1_700_000_000,
            'updated_at' => 1_700_000_000,
        ];

        $payload = [
            'country_code' => 'us',
            'user_agent' => 'Mozilla/5.0 (Android)',
        ];

        $result = \SRP\decisionResponse(
            $payload,
            $vpnResolver,
            $configResolver,
            null,
            static fn (): int => 1_700_000_050
        );

        self::assertFalse($vpnCalled);
        self::assertSame('A', $result['response']['decision']);
        self::assertSame('https://example.com/target', $result['response']['target']);
        self::assertFalse($result['response']['meta']['vpn']);
    }
}
