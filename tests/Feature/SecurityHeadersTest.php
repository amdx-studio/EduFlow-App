<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Tests\TestCase;

/**
 * TC-061: header keamanan pada respons.
 */
class SecurityHeadersTest extends TestCase
{
    private function production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }

    public function test_tc061_basic_headers_on_every_response_but_no_csp_outside_production(): void
    {
        foreach (['/api/user', '/up'] as $url) {
            $response = $this->get($url, ['Accept' => 'application/json']);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->assertHeaderMissing('Content-Security-Policy');
            $response->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_tc061_production_adds_a_strict_csp_without_unsafe_sources(): void
    {
        $this->production();

        $response = $this->getJson('/api/user')->assertUnauthorized();

        $response->assertHeader('Content-Security-Policy', SecurityHeaders::CSP);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('*', $csp);
    }

    public function test_tc061_hsts_only_on_https_in_production(): void
    {
        $this->production();

        $this->getJson('/api/user')->assertHeaderMissing('Strict-Transport-Security');

        $this
            ->getJson('https://localhost/api/user')
            ->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS);
    }
}
