<?php

namespace Tests\Unit\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\PawPassNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class WebPushChannelTest extends TestCase
{
    use RefreshDatabase;

    private function makeMockReport(bool $success, int $statusCode = 201): MessageSentReport
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        return new MessageSentReport($request, $response, $success);
    }

    public function test_sends_to_all_user_subscriptions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        PushSubscription::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'endpoint' => 'https://push.example.com/sub1',
            'p256dh' => 'key1',
            'auth_token' => 'auth1',
        ]);
        PushSubscription::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'endpoint' => 'https://push.example.com/sub2',
            'p256dh' => 'key2',
            'auth_token' => 'auth2',
        ]);

        $webPush = $this->createMock(WebPush::class);
        $webPush->expects($this->exactly(2))
            ->method('sendOneNotification')
            ->willReturn($this->makeMockReport(true, 201));

        $channel = new WebPushChannel($webPush);
        $notification = new PawPassNotification('payment.confirmed', $tenant->id);

        $channel->send($user, $notification);
    }

    public function test_deletes_expired_subscription_on_410(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        PushSubscription::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'endpoint' => 'https://push.example.com/gone',
            'p256dh' => 'key1',
            'auth_token' => 'auth1',
        ]);

        $webPush = $this->createMock(WebPush::class);
        $webPush->method('sendOneNotification')
            ->willReturn($this->makeMockReport(false, 410));

        $channel = new WebPushChannel($webPush);
        $channel->send($user, new PawPassNotification('credits.low', $tenant->id));

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/gone',
        ]);
    }

    public function test_does_nothing_when_user_has_no_subscriptions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $webPush = $this->createMock(WebPush::class);
        $webPush->expects($this->never())->method('sendOneNotification');

        $channel = new WebPushChannel($webPush);
        $channel->send($user, new PawPassNotification('payment.confirmed', $tenant->id));
    }
}
