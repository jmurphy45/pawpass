<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendTrialDripEmails;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TenantEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendTrialDripEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function trialTenant(array $overrides = []): array
    {
        $tenant = Tenant::factory()->create(array_merge([
            'status' => 'trialing',
            'trial_started_at' => now()->subDays(2),
        ], $overrides));

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'business_owner',
        ]);

        $tenant->update(['owner_user_id' => $owner->id]);

        return [$tenant->fresh(), $owner];
    }

    private function runJob(?NotificationService $notifications = null): void
    {
        (new SendTrialDripEmails)->handle(
            $notifications ?? $this->mock(NotificationService::class)->shouldIgnoreMissing()->getMock(),
            app(TenantEventService::class),
        );
    }

    // ─── Stripe nudge ────────────────────────────────────────────────────────────

    public function test_stripe_nudge_sent_on_day_1_when_stripe_not_connected(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(1),
            'stripe_account_id' => null,
        ]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with('onboarding.stripe_nudge', $tenant->id, $owner->id, Mockery::any())
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    public function test_stripe_nudge_skipped_when_stripe_already_connected(): void
    {
        [$tenant] = $this->trialTenant([
            'trial_started_at' => now()->subDays(1),
            'stripe_account_id' => 'acct_test123',
        ]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch')->with('onboarding.stripe_nudge', Mockery::any(), Mockery::any(), Mockery::any());
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    public function test_stripe_nudge_not_resent_when_already_sent(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(2),
            'stripe_account_id' => null,
        ]);

        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.stripe_nudge']);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch')->with('onboarding.stripe_nudge', Mockery::any(), Mockery::any(), Mockery::any());
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    // ─── First customer nudge ────────────────────────────────────────────────────

    public function test_first_customer_nudge_sent_on_day_3_with_no_customers(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(3),
            'stripe_account_id' => 'acct_test',
        ]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with('onboarding.first_customer_nudge', $tenant->id, $owner->id, Mockery::any())
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    public function test_first_customer_nudge_skipped_when_customers_exist(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(3),
            'stripe_account_id' => 'acct_test',
        ]);

        Customer::factory()->create(['tenant_id' => $tenant->id]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch')->with('onboarding.first_customer_nudge', Mockery::any(), Mockery::any(), Mockery::any());
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    // ─── First check-in nudge ────────────────────────────────────────────────────

    public function test_first_checkin_nudge_sent_on_day_7_with_no_attendances(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(7),
            'stripe_account_id' => 'acct_test',
        ]);

        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.first_customer_nudge']);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with('onboarding.first_checkin_nudge', $tenant->id, $owner->id, Mockery::any())
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    public function test_first_checkin_nudge_skipped_when_attendance_exists(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(7),
            'stripe_account_id' => 'acct_test',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'customer']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();
        Attendance::factory()->create(['tenant_id' => $tenant->id, 'dog_id' => $dog->id]);

        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.first_customer_nudge']);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch')->with('onboarding.first_checkin_nudge', Mockery::any(), Mockery::any(), Mockery::any());
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    // ─── Halfway upgrade nudge ───────────────────────────────────────────────────

    public function test_halfway_nudge_sent_on_day_10(): void
    {
        [$tenant, $owner] = $this->trialTenant([
            'trial_started_at' => now()->subDays(10),
            'stripe_account_id' => 'acct_test',
        ]);

        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.stripe_nudge']);
        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.first_customer_nudge']);
        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.first_checkin_nudge']);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with('onboarding.halfway_upgrade', $tenant->id, $owner->id, Mockery::any())
            ->once();
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    public function test_halfway_nudge_not_resent(): void
    {
        [$tenant] = $this->trialTenant([
            'trial_started_at' => now()->subDays(12),
            'stripe_account_id' => 'acct_test',
        ]);

        TenantEvent::create(['tenant_id' => $tenant->id, 'event_type' => 'email_drip.halfway_upgrade']);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch')->with('onboarding.halfway_upgrade', Mockery::any(), Mockery::any(), Mockery::any());
        $notifications->shouldReceive('dispatch')->withAnyArgs()->zeroOrMoreTimes();

        $this->runJob($notifications);
    }

    // ─── Non-trialing tenants ignored ───────────────────────────────────────────

    public function test_active_tenant_not_processed(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'business_owner']);
        $tenant->update(['owner_user_id' => $owner->id]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch');

        $this->runJob($notifications);
    }

    public function test_tenant_without_owner_skipped(): void
    {
        Tenant::factory()->create([
            'status' => 'trialing',
            'trial_started_at' => now()->subDays(5),
            'owner_user_id' => null,
        ]);

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldNotReceive('dispatch');

        $this->runJob($notifications);
    }
}
