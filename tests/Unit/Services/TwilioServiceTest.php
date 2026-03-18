<?php

namespace Tests\Unit\Services;

use App\Services\TwilioService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioServiceTest extends TestCase
{
    private TwilioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TwilioService(
            sid: 'AC_test_sid',
            token: 'test_token',
            from: '+15005550006',
        );
    }

    public function test_send_returns_one_segment_for_short_message(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $result = $this->service->send('+15005550006', 'Hello World');

        $this->assertSame(1, $result);
    }

    public function test_send_returns_one_segment_for_exactly_160_chars(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $message = str_repeat('a', 160);
        $result  = $this->service->send('+15005550006', $message);

        $this->assertSame(1, $result);
    }

    public function test_send_returns_two_segments_for_161_chars(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $message = str_repeat('a', 161);
        $result  = $this->service->send('+15005550006', $message);

        $this->assertSame(2, $result);
    }

    public function test_send_returns_two_segments_for_320_chars(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $message = str_repeat('a', 320);
        $result  = $this->service->send('+15005550006', $message);

        $this->assertSame(2, $result);
    }

    public function test_send_throws_on_failure_response(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'Bad request'], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Twilio SMS failed/');

        $this->service->send('+15005550006', 'Hello');
    }
}
