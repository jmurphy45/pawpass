<?php

namespace Tests\Feature\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\PlanFeatureCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PawPassNotificationWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tomail_includes_branding_when_tenant_has_white_label_feature(): void
    {
        $tenant = Tenant::factory()->create([
            'plan'          => 'business',
            'logo_url'      => 'https://example.com/logo.png',
            'primary_color' => '#ff0000',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        app()->forgetInstance(PlanFeatureCache::class);
        $cache = $this->mock(PlanFeatureCache::class);
        $cache->shouldReceive('hasFeature')->with('business', 'white_label')->andReturn(true);

        $notification = new PawPassNotification('payment.confirmed', $tenant->id);
        $mailMessage = $notification->toMail($user);

        $this->assertArrayHasKey('logoUrl', $mailMessage->viewData);
        $this->assertEquals('https://example.com/logo.png', $mailMessage->viewData['logoUrl']);
        $this->assertArrayHasKey('primaryColor', $mailMessage->viewData);
        $this->assertEquals('#ff0000', $mailMessage->viewData['primaryColor']);
    }

    public function test_tomail_excludes_branding_when_tenant_lacks_white_label_feature(): void
    {
        $tenant = Tenant::factory()->create([
            'plan'          => 'starter',
            'logo_url'      => 'https://example.com/logo.png',
            'primary_color' => '#ff0000',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        app()->forgetInstance(PlanFeatureCache::class);
        $cache = $this->mock(PlanFeatureCache::class);
        $cache->shouldReceive('hasFeature')->with('starter', 'white_label')->andReturn(false);

        $notification = new PawPassNotification('payment.confirmed', $tenant->id);
        $mailMessage = $notification->toMail($user);

        $this->assertArrayNotHasKey('logoUrl', $mailMessage->viewData);
        $this->assertArrayNotHasKey('primaryColor', $mailMessage->viewData);
    }

    public function test_tomail_does_not_error_when_notifiable_has_no_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'role'      => 'platform_admin',
        ]);

        $notification = new PawPassNotification('announcement', 'some-tenant-id', ['subject' => 'Hi', 'body' => 'Test']);
        $mailMessage = $notification->toMail($user);

        $this->assertNotNull($mailMessage);
        $this->assertArrayNotHasKey('logoUrl', $mailMessage->viewData);
    }
}
