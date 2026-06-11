<?php

namespace Tests\Unit\Services;

use App\Services\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    private QrCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QrCodeService::class);
    }

    public function test_generate_token_returns_numeric_string(): void
    {
        $token = $this->service->generateToken();

        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^\d+$/', $token);
        $this->assertGreaterThan(10, strlen($token));
    }

    public function test_svg_returns_svg_string(): void
    {
        $result = $this->service->svg('https://pawpass.com/go/123456789');

        $this->assertStringContainsString('<svg', $result);
    }

    public function test_png_returns_png_binary(): void
    {
        $result = $this->service->png('https://pawpass.com/go/123456789');

        $this->assertStringStartsWith("\x89PNG", $result);
    }

    public function test_stable_url_includes_token(): void
    {
        $token = '1234567890123456789';
        $url = $this->service->stableUrl($token);

        $this->assertStringStartsWith('https://'.config('app.domain').'/go/', $url);
        $this->assertStringContainsString('/go/'.$token, $url);
    }

    public function test_two_tokens_are_unique(): void
    {
        $a = $this->service->generateToken();
        $b = $this->service->generateToken();

        $this->assertNotSame($a, $b);
    }
}
