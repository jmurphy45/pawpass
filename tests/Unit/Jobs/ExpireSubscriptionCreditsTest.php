<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExpireSubscriptionCredits;
use App\Jobs\ProcessAutoReplenishJob;
use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireSubscriptionCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    private function makeDog(int $credits = 0): Dog
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        return Dog::factory()->create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'credit_balance' => $credits,
        ]);
    }

    public function test_subscription_expiry_wipes_all_credits(): void
    {
        $dog = $this->makeDog(8);
        $dog->update(['credits_expire_at' => now()->subMinute()]);

        (new ExpireSubscriptionCredits)->handle();

        $fresh = $dog->fresh();
        $this->assertSame(0, $fresh->credit_balance);
        $this->assertNull($fresh->credits_expire_at);
    }

    public function test_unlimited_pass_expiry_removes_only_pass_credits(): void
    {
        // Dog has 3 one-time purchase credits + 28 pass credits = 31 total
        $dog = $this->makeDog(31);
        $passExpiresAt = now()->subMinute();
        $dog->update(['unlimited_pass_expires_at' => $passExpiresAt]);

        CreditLedger::allTenants()->newQuery()->insert([
            'id'            => \Illuminate\Support\Str::ulid(),
            'tenant_id'     => $dog->tenant_id,
            'dog_id'        => $dog->id,
            'type'          => 'subscription',
            'delta'         => 28,
            'balance_after' => 31,
            'expires_at'    => $passExpiresAt,
            'created_at'    => now()->subDay(),
        ]);

        (new ExpireSubscriptionCredits)->handle();

        $fresh = $dog->fresh();
        $this->assertSame(3, $fresh->credit_balance);
        $this->assertNull($fresh->unlimited_pass_expires_at);
    }

    public function test_unlimited_pass_expiry_does_not_touch_credits_expire_at(): void
    {
        $dog = $this->makeDog(10);
        $passExpiresAt = now()->subMinute();
        $dog->update(['unlimited_pass_expires_at' => $passExpiresAt]);

        CreditLedger::allTenants()->newQuery()->insert([
            'id'            => \Illuminate\Support\Str::ulid(),
            'tenant_id'     => $dog->tenant_id,
            'dog_id'        => $dog->id,
            'type'          => 'subscription',
            'delta'         => 10,
            'balance_after' => 10,
            'expires_at'    => $passExpiresAt,
            'created_at'    => now()->subDay(),
        ]);

        (new ExpireSubscriptionCredits)->handle();

        // credits_expire_at was never set, so it stays null
        $this->assertNull($dog->fresh()->credits_expire_at);
    }

    public function test_job_implements_should_be_unique_to_prevent_concurrent_double_runs(): void
    {
        $this->assertInstanceOf(ShouldBeUnique::class, new ExpireSubscriptionCredits);
    }

    public function test_running_twice_sequentially_dispatches_auto_replenish_only_once(): void
    {
        Queue::fake();

        $dog = $this->makeDog(0);
        $dog->update([
            'unlimited_pass_expires_at' => now()->subMinute(),
            'auto_replenish_enabled'    => true,
        ]);

        // First run: should dispatch ProcessAutoReplenishJob and clear the expiry date
        (new ExpireSubscriptionCredits)->handle();
        Queue::assertPushed(ProcessAutoReplenishJob::class, 1);

        // Second run: expiry date cleared, dog not in query — no second dispatch
        (new ExpireSubscriptionCredits)->handle();
        Queue::assertPushed(ProcessAutoReplenishJob::class, 1);
    }

    public function test_future_unlimited_pass_is_not_expired(): void
    {
        $dog = $this->makeDog(28);
        $dog->update(['unlimited_pass_expires_at' => now()->addDays(5)]);

        (new ExpireSubscriptionCredits)->handle();

        $fresh = $dog->fresh();
        $this->assertSame(28, $fresh->credit_balance);
        $this->assertNotNull($fresh->unlimited_pass_expires_at);
    }
}
