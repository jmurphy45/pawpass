<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExpireSubscriptionCredits;
use App\Jobs\PruneDispatchedPending;
use App\Jobs\PruneOldNotifications;
use App\Jobs\PruneRawWebhooks;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Notification;
use App\Models\NotificationPending;
use App\Models\RawWebhook;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    // ─── ExpireSubscriptionCredits ───────────────────────────────────────────────

    public function test_expire_credits_zeroes_balance_for_past_expiry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create([
            'credits_expire_at' => now()->subDay(),
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new ExpireSubscriptionCredits)->handle();

        $this->assertSame(0, $dog->fresh()->credit_balance);
    }

    public function test_expire_credits_clears_expiry_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create([
            'credits_expire_at' => now()->subDay(),
            'credits_alert_sent_at' => now()->subHour(),
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new ExpireSubscriptionCredits)->handle();

        $fresh = $dog->fresh();
        $this->assertNull($fresh->credits_expire_at);
        $this->assertNull($fresh->credits_alert_sent_at);
    }

    public function test_expire_credits_does_not_affect_future_expiry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create([
            'credits_expire_at' => now()->addDay(),
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new ExpireSubscriptionCredits)->handle();

        $this->assertSame(5, $dog->fresh()->credit_balance);
    }

    public function test_expire_credits_writes_expiry_removal_ledger_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create([
            'credits_expire_at' => now()->subDay(),
        ]);

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        (new ExpireSubscriptionCredits)->handle();

        $this->assertDatabaseHas('credit_ledger', [
            'dog_id' => $dog->id,
            'type' => 'expiry_removal',
            'delta' => -5,
            'balance_after' => 0,
        ]);
    }

    public function test_expire_credits_dispatches_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $dog = Dog::factory()->forCustomer($customer)->withCredits(5)->create([
            'credits_expire_at' => now()->subDay(),
        ]);

        $notif = $this->mock(NotificationService::class);
        $notif->shouldReceive('dispatch')
            ->once()
            ->with('credits.empty', $tenant->id, $user->id, \Mockery::type('array'));

        (new ExpireSubscriptionCredits)->handle();
    }

    // ─── PruneOldNotifications ────────────────────────────────────────────────

    public function test_prune_notifications_deletes_old_records(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $data = json_encode(['type' => 'payment.confirmed', 'tenant_id' => $tenant->id]);

        // Old notification (> 90 days)
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => PawPassNotification::class,
            'data' => $data,
            'tenant_id' => $tenant->id,
            'created_at' => now()->subDays(91),
            'updated_at' => now()->subDays(91),
        ]);

        // Recent notification
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => PawPassNotification::class,
            'data' => $data,
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new PruneOldNotifications)->handle();

        $this->assertSame(1, Notification::allTenants()->count());
    }

    // ─── PruneRawWebhooks ─────────────────────────────────────────────────────

    public function test_prune_raw_webhooks_deletes_old_records(): void
    {
        RawWebhook::create([
            'provider' => 'stripe',
            'event_id' => 'evt_old',
            'payload' => '{}',
            'received_at' => now()->subDays(8),
        ]);

        RawWebhook::create([
            'provider' => 'stripe',
            'event_id' => 'evt_new',
            'payload' => '{}',
            'received_at' => now()->subDays(1),
        ]);

        (new PruneRawWebhooks)->handle();

        $this->assertSame(1, RawWebhook::count());
        $this->assertDatabaseHas('raw_webhooks', ['event_id' => 'evt_new']);
    }

    // ─── PruneDispatchedPending ───────────────────────────────────────────────

    public function test_prune_dispatched_pending_deletes_old_records(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [],
            'dispatched_at' => now()->subHours(25),
        ]);

        NotificationPending::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'credits.low',
            'dog_ids' => [],
            'dispatched_at' => null,
        ]);

        (new PruneDispatchedPending)->handle();

        $this->assertSame(1, NotificationPending::allTenants()->count());
        $this->assertNull(NotificationPending::allTenants()->first()->dispatched_at);
    }
}
