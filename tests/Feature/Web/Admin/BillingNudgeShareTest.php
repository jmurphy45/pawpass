<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BillingNudgeShareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'testco', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);
    }

    public function test_billing_pm_attached_at_defaults_to_null(): void
    {
        $this->assertNull($this->tenant->billing_pm_attached_at);
    }

    public function test_billing_pm_attached_at_can_be_set(): void
    {
        $this->tenant->update(['billing_pm_attached_at' => now()]);

        $this->tenant->refresh();

        $this->assertNotNull($this->tenant->billing_pm_attached_at);
    }

    public function test_shared_props_include_trial_ends_at_for_trialing_tenant(): void
    {
        $trialEnd = now()->addDays(5);
        $this->tenant->update(['status' => 'trialing', 'trial_ends_at' => $trialEnd]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('tenantTrialEndsAt', fn ($val) => $val !== null)
            ->where('tenantBillingPmAttached', false)
        );
    }

    public function test_shared_props_trial_ends_at_is_null_when_not_set(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('tenantTrialEndsAt', null)
        );
    }

    public function test_shared_props_billing_pm_attached_is_true_when_set(): void
    {
        $this->tenant->update(['billing_pm_attached_at' => now()]);

        $this->actingAs($this->owner);

        $response = $this->get('/admin');

        $response->assertInertia(fn ($page) => $page
            ->where('tenantBillingPmAttached', true)
        );
    }
}
