<?php

namespace Tests\Feature\Web\Admin;

use App\Mail\CustomerWelcomeMail;
use App\Mail\MagicLinkMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanFeatureCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedMailTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $attrs = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'logo_url'      => 'https://cdn.example.com/logo.png',
            'primary_color' => '#cc3300',
        ], $attrs));
    }

    private function mockFeature(string $plan, bool $hasWhiteLabel): void
    {
        app()->forgetInstance(PlanFeatureCache::class);
        $cache = $this->mock(PlanFeatureCache::class);
        $cache->shouldReceive('hasFeature')
            ->with($plan, 'white_label')
            ->andReturn($hasWhiteLabel);
    }

    // ─── CustomerWelcomeMail ────────────────────────────────────────────────

    public function test_customer_welcome_includes_logo_when_white_label_active(): void
    {
        $tenant = $this->makeTenant(['plan' => 'business']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('business', true);

        $mail = new CustomerWelcomeMail($user, $tenant, 'raw-token');
        $html = $mail->render();

        $this->assertStringContainsString('https://cdn.example.com/logo.png', $html);
    }

    public function test_customer_welcome_uses_primary_color_when_white_label_active(): void
    {
        $tenant = $this->makeTenant(['plan' => 'business']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('business', true);

        $mail = new CustomerWelcomeMail($user, $tenant, 'raw-token');
        $html = $mail->render();

        $this->assertStringContainsString('#cc3300', $html);
    }

    public function test_customer_welcome_omits_logo_when_white_label_inactive(): void
    {
        $tenant = $this->makeTenant(['plan' => 'starter']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('starter', false);

        $mail = new CustomerWelcomeMail($user, $tenant, 'raw-token');
        $html = $mail->render();

        $this->assertStringNotContainsString('https://cdn.example.com/logo.png', $html);
    }

    public function test_customer_welcome_uses_default_color_when_white_label_inactive(): void
    {
        $tenant = $this->makeTenant(['plan' => 'starter', 'primary_color' => '#cc3300']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('starter', false);

        $mail = new CustomerWelcomeMail($user, $tenant, 'raw-token');
        $html = $mail->render();

        $this->assertStringContainsString('#4f46e5', $html);
        $this->assertStringNotContainsString('#cc3300', $html);
    }

    // ─── MagicLinkMail ──────────────────────────────────────────────────────

    public function test_magic_link_includes_logo_when_white_label_active(): void
    {
        $tenant = $this->makeTenant(['plan' => 'business']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('business', true);

        $mail = new MagicLinkMail($user, 'raw-token', $tenant);
        $html = $mail->render();

        $this->assertStringContainsString('https://cdn.example.com/logo.png', $html);
    }

    public function test_magic_link_uses_primary_color_when_white_label_active(): void
    {
        $tenant = $this->makeTenant(['plan' => 'business']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('business', true);

        $mail = new MagicLinkMail($user, 'raw-token', $tenant);
        $html = $mail->render();

        $this->assertStringContainsString('#cc3300', $html);
    }

    public function test_magic_link_omits_logo_when_white_label_inactive(): void
    {
        $tenant = $this->makeTenant(['plan' => 'starter']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->mockFeature('starter', false);

        $mail = new MagicLinkMail($user, 'raw-token', $tenant);
        $html = $mail->render();

        $this->assertStringNotContainsString('https://cdn.example.com/logo.png', $html);
    }

    public function test_magic_link_does_not_error_when_tenant_is_null(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'role' => 'platform_admin']);

        $mail = new MagicLinkMail($user, 'raw-token', null);
        $html = $mail->render();

        $this->assertNotEmpty($html);
    }
}
