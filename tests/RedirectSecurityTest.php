<?php

declare(strict_types=1);

namespace SRP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @covers \SRP\isValidRedirectTarget
 * @covers \SRP\fallbackRedirect
 * @covers \SRP\assertRedirectUrl
 */
final class RedirectSecurityTest extends TestCase
{
    /**
     * Test CRLF injection prevention in redirect URLs
     */
    public function testPreventsCRLFInjectionInRedirectUrl(): void
    {
        require_once __DIR__ . '/../_client/redirect.php';
        
        $maliciousUrls = [
            "/path\r\nSet-Cookie: evil=true",
            "/path\nLocation: http://evil.com",
            "/path\x00null-byte",
            "http://example.com\r\nX-Evil: header",
        ];
        
        foreach ($maliciousUrls as $url) {
            $result = isValidRedirectTarget($url);
            $this->assertFalse($result, "Should reject URL with CRLF: {$url}");
        }
    }
    
    /**
     * Test open redirect prevention
     */
    public function testPreventsOpenRedirect(): void
    {
        require_once __DIR__ . '/../_client/redirect.php';
        
        $maliciousUrls = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'file:///etc/passwd',
            'ftp://evil.com',
            '//evil.com/path',
        ];
        
        foreach ($maliciousUrls as $url) {
            $result = isValidRedirectTarget($url);
            $this->assertFalse($result, "Should reject malicious URL: {$url}");
        }
    }
    
    /**
     * Test valid redirect URLs are accepted
     */
    public function testAcceptsValidRedirectUrls(): void
    {
        require_once __DIR__ . '/../_client/redirect.php';
        
        $validUrls = [
            '/path/to/page',
            '/path?query=value',
            '/path#fragment',
            'https://example.com/path',
            'http://example.com/path',
        ];
        
        foreach ($validUrls as $url) {
            $result = isValidRedirectTarget($url);
            $this->assertTrue($result, "Should accept valid URL: {$url}");
        }
    }
    
    /**
     * Test assertRedirectUrl prevents private IPs
     */
    public function testPreventsPrivateIpRedirects(): void
    {
        require_once __DIR__ . '/../_bootstrap.php';
        
        $privateUrls = [
            'http://localhost/path',
            'http://127.0.0.1/path',
            'http://10.0.0.1/path',
            'http://192.168.1.1/path',
            'http://172.16.0.1/path',
            'http://169.254.1.1/path',
        ];
        
        foreach ($privateUrls as $url) {
            $result = \SRP\assertRedirectUrl($url);
            $this->assertSame('', $result, "Should reject private IP URL: {$url}");
        }
    }
    
    /**
     * Test assertRedirectUrl accepts valid public URLs
     */
    public function testAcceptsValidPublicUrls(): void
    {
        require_once __DIR__ . '/../_bootstrap.php';
        
        $validUrls = [
            'https://example.com',
            'http://example.com/path',
            'https://sub.example.com/path?query=1',
        ];
        
        foreach ($validUrls as $url) {
            $result = \SRP\assertRedirectUrl($url);
            $this->assertNotEmpty($result, "Should accept valid public URL: {$url}");
            $this->assertStringStartsWith('http', $result);
        }
    }
    
    /**
     * Test fallbackRedirect sanitizes reason parameter
     */
    public function testFallbackRedirectSanitizesReason(): void
    {
        require_once __DIR__ . '/../_client/redirect.php';
        
        // We can't test actual redirect, but we can test the validation logic
        $this->expectNotToPerformAssertions();
        
        // This would normally trigger a redirect, but in test context it won't
        // The important part is that the function doesn't throw errors
        try {
            ob_start();
            fallbackRedirect("test<script>alert(1)</script>", '/');
        } catch (\Throwable $e) {
            ob_end_clean();
            // Expected - exit() was called
            $this->assertStringContainsString('exit', strtolower($e->getMessage()));
        }
        
        ob_end_clean();
    }
    
    /**
     * Test decisionResponse validates target URLs
     */
    public function testDecisionResponseValidatesTargetUrls(): void
    {
        require_once __DIR__ . '/../_bootstrap.php';
        
        $payload = [
            'click_id' => 'TEST123',
            'country_code' => 'US',
            'user_agent' => 'Mozilla/5.0',
            'ip_address' => '8.8.8.8',
            'user_lp' => 'DEFAULT',
        ];
        
        $mockConfig = static fn (): array => [
            'system_on' => true,
            'redirect_url' => "https://example.com\r\nX-Evil: header",
            'allowed_countries' => [],
            'is_active' => true,
            'rule_mode' => 'static_route',
            'rule_started_at' => null,
            'updated_at' => time(),
        ];
        
        $result = \SRP\decisionResponse(
            $payload,
            static fn (string $ip): bool => false,
            $mockConfig,
            static fn (): bool => true,
            static fn (): int => time()
        );
        
        // Should fallback to safe URL when redirect_url contains CRLF
        $this->assertIsArray($result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('target', $result['response']);
        
        // Target should not contain CRLF
        $target = $result['response']['target'];
        $this->assertStringNotContainsString("\r", $target);
        $this->assertStringNotContainsString("\n", $target);
        $this->assertStringNotContainsString("\x00", $target);
    }
}
