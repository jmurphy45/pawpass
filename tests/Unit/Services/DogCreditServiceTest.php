<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Jobs\ProcessAutoReplenishJob;
use App\Models\Attendance;
use App\Models\CreditLedger;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class DogCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private DogCreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DogCreditService;
        app()->forgetInstance('current.tenant.id');
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    private function makeDog(int $credits = 0, ?Customer $customer = null): Dog
    {
        $tenant = Tenant::factory()->create();
        $customer ??= Customer::factory()->create(['tenant_id' => $tenant->id]);

        return Dog::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'credit_balance' => $credits,
        ]);
    }

    private function makeOrder(Dog $dog, int $creditCount = 10): Order
    {
        $package = Package::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'credit_count' => $creditCount,
        ]);

        return Order::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id' => $package->id,
        ]);
    }

    private function makeUnlimitedOrder(Dog $dog, int $days = 30): Order
    {
        $package = Package::factory()->unlimited($days)->create([
            'tenant_id' => $dog->tenant_id,
        ]);

        return Order::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id' => $package->id,
        ]);
    }

    // -----------------------------------------------------------
    // issueUnlimitedPass
    // -----------------------------------------------------------

    public function test_issue_unlimited_pass_issues_days_in_month_credits(): void
    {
        $dog = $this->makeDog(0);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->issueUnlimitedPass($order, $dog);

        $fresh = $dog->fresh();
        $this->assertSame(now()->daysInMonth, $fresh->credit_balance);
        $this->assertNotNull($fresh->unlimited_pass_expires_at);
    }

    public function test_issue_unlimited_pass_sets_only_unlimited_pass_expires_at(): void
    {
        $dog = $this->makeDog(0);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->issueUnlimitedPass($order, $dog);

        $fresh = $dog->fresh();
        $this->assertNull($fresh->credits_expire_at);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $fresh->unlimited_pass_expires_at->timestamp, 5);
    }

    public function test_issue_unlimited_pass_creates_purchase_ledger_entry_with_days_in_month_delta(): void
    {
        $dog = $this->makeDog(5);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->issueUnlimitedPass($order, $dog);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('purchase', $entry->type);
        $this->assertSame(now()->daysInMonth, $entry->delta);
        $this->assertSame(5 + now()->daysInMonth, $entry->balance_after);
        $this->assertSame($order->id, $entry->order_id);
        $this->assertNotNull($entry->expires_at);
    }

    // -----------------------------------------------------------
    // revokeUnlimitedPass
    // -----------------------------------------------------------

    public function test_revoke_unlimited_pass_zeros_credit_balance_and_clears_unlimited_pass(): void
    {
        $dog = $this->makeDog(28);
        $dog->update(['unlimited_pass_expires_at' => now()->addDays(20)]);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->revokeUnlimitedPass($order, $dog);

        $fresh = $dog->fresh();
        $this->assertSame(0, $fresh->credit_balance);
        $this->assertNull($fresh->unlimited_pass_expires_at);
    }

    public function test_revoke_unlimited_pass_creates_refund_entry_with_negative_delta(): void
    {
        $dog = $this->makeDog(5);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->revokeUnlimitedPass($order, $dog);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('refund', $entry->type);
        $this->assertSame(-5, $entry->delta);
        $this->assertSame(0, $entry->balance_after);
        $this->assertSame($order->id, $entry->order_id);
    }

    public function test_revoke_unlimited_pass_does_nothing_when_balance_zero(): void
    {
        $dog = $this->makeDog(0);
        $order = $this->makeUnlimitedOrder($dog, 30);

        $this->service->revokeUnlimitedPass($order, $dog);

        $this->assertDatabaseCount('credit_ledger', 0);
    }

    // -----------------------------------------------------------
    // issueFromOrder
    // -----------------------------------------------------------

    public function test_issue_from_order_creates_purchase_ledger_entry(): void
    {
        $dog = $this->makeDog(0);
        $order = $this->makeOrder($dog, 10);

        $this->service->issueFromOrder($order, $dog);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('purchase', $entry->type);
        $this->assertSame(10, $entry->delta);
        $this->assertSame(10, $entry->balance_after);
        $this->assertSame($order->id, $entry->order_id);
    }

    public function test_issue_from_order_increments_credit_balance(): void
    {
        $dog = $this->makeDog(5);
        $order = $this->makeOrder($dog, 10);

        $this->service->issueFromOrder($order, $dog);

        $this->assertSame(15, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // deductForAttendance
    // -----------------------------------------------------------

    public function test_deduct_for_attendance_creates_deduction_entry(): void
    {
        $dog = $this->makeDog(5);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
            'zero_credit_override' => false,
        ]);

        $this->service->deductForAttendance($attendance);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('deduction', $entry->type);
        $this->assertSame(-1, $entry->delta);
        $this->assertSame(4, $entry->balance_after);
    }

    public function test_deduct_decrements_credit_balance(): void
    {
        $dog = $this->makeDog(3);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
        ]);

        $this->service->deductForAttendance($attendance);

        $this->assertSame(2, $dog->fresh()->credit_balance);
    }

    public function test_deduct_throws_when_no_credits_and_no_override(): void
    {
        $this->expectException(InsufficientCreditsException::class);

        $dog = $this->makeDog(0);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
            'zero_credit_override' => false,
        ]);

        $this->service->deductForAttendance($attendance);
    }

    public function test_deduct_allows_zero_balance_with_override(): void
    {
        $dog = $this->makeDog(0);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
            'zero_credit_override' => true,
            'override_note' => 'Owner approved',
        ]);

        $this->service->deductForAttendance($attendance);

        $this->assertSame(-1, $dog->fresh()->credit_balance);
    }

    public function test_deduct_skips_when_active_unlimited_pass(): void
    {
        $dog = $this->makeDog(0);
        $dog->update(['unlimited_pass_expires_at' => now()->addDays(30)]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
            'zero_credit_override' => false,
        ]);

        $this->service->deductForAttendance($attendance);

        $this->assertSame(0, $dog->fresh()->credit_balance);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_deduct_proceeds_when_unlimited_pass_expired(): void
    {
        $dog = $this->makeDog(5);
        $dog->update(['unlimited_pass_expires_at' => now()->subDay()]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'dog_id' => $dog->id,
            'zero_credit_override' => false,
        ]);

        $this->service->deductForAttendance($attendance);

        $this->assertSame(4, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // removeAllOnRefund
    // -----------------------------------------------------------

    public function test_remove_all_on_refund_zeros_balance(): void
    {
        $dog = $this->makeDog(8);
        $order = $this->makeOrder($dog);

        $this->service->removeAllOnRefund($order, $dog);

        $this->assertSame(0, $dog->fresh()->credit_balance);
    }

    public function test_remove_all_on_refund_creates_refund_entry(): void
    {
        $dog = $this->makeDog(8);
        $order = $this->makeOrder($dog);

        $this->service->removeAllOnRefund($order, $dog);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('refund', $entry->type);
        $this->assertSame(-8, $entry->delta);
        $this->assertSame(0, $entry->balance_after);
    }

    public function test_remove_all_on_refund_does_nothing_when_balance_zero(): void
    {
        $dog = $this->makeDog(0);
        $order = $this->makeOrder($dog);

        $this->service->removeAllOnRefund($order, $dog);

        $this->assertDatabaseCount('credit_ledger', 0);
    }

    // -----------------------------------------------------------
    // addGoodwill
    // -----------------------------------------------------------

    public function test_add_goodwill_creates_goodwill_entry(): void
    {
        $dog = $this->makeDog(2);
        $admin = User::factory()->create(['tenant_id' => $dog->tenant_id, 'role' => 'business_owner']);

        $this->service->addGoodwill($dog, 5, 'Sorry for the inconvenience', $admin);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('goodwill', $entry->type);
        $this->assertSame(5, $entry->delta);
        $this->assertSame(7, $entry->balance_after);
        $this->assertSame('Sorry for the inconvenience', $entry->note);
        $this->assertSame($admin->id, $entry->created_by);
    }

    public function test_add_goodwill_increments_balance(): void
    {
        $dog = $this->makeDog(2);
        $admin = User::factory()->create(['tenant_id' => $dog->tenant_id]);

        $this->service->addGoodwill($dog, 3, 'note', $admin);

        $this->assertSame(5, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // applyCorrection
    // -----------------------------------------------------------

    public function test_apply_correction_positive_delta_creates_correction_add(): void
    {
        $dog = $this->makeDog(5);
        $admin = User::factory()->create(['tenant_id' => $dog->tenant_id]);

        $this->service->applyCorrection($dog, 3, 'Adding missed credits', $admin);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('correction_add', $entry->type);
        $this->assertSame(3, $entry->delta);
        $this->assertSame(8, $entry->balance_after);
    }

    public function test_apply_correction_negative_delta_creates_correction_remove(): void
    {
        $dog = $this->makeDog(5);
        $admin = User::factory()->create(['tenant_id' => $dog->tenant_id]);

        $this->service->applyCorrection($dog, -2, 'Removing duplicate credits', $admin);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('correction_remove', $entry->type);
        $this->assertSame(-2, $entry->delta);
        $this->assertSame(3, $entry->balance_after);
        $this->assertSame(3, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // transfer
    // -----------------------------------------------------------

    public function test_transfer_moves_credits_between_dogs(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $from = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'credit_balance' => 10]);
        $to = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'credit_balance' => 2]);

        $this->service->transfer($from, $to, 4);

        $this->assertSame(6, $from->fresh()->credit_balance);
        $this->assertSame(6, $to->fresh()->credit_balance);
    }

    public function test_transfer_creates_linked_ledger_entries(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $from = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'credit_balance' => 10]);
        $to = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'credit_balance' => 2]);

        $this->service->transfer($from, $to, 4);

        $outEntry = CreditLedger::allTenants()->where('type', 'transfer_out')->first();
        $inEntry = CreditLedger::allTenants()->where('type', 'transfer_in')->first();

        $this->assertNotNull($outEntry);
        $this->assertNotNull($inEntry);
        $this->assertSame(-4, $outEntry->delta);
        $this->assertSame(4, $inEntry->delta);
        $this->assertSame($outEntry->id, $inEntry->parent_ledger_id);
    }

    public function test_transfer_throws_for_different_customers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $tenant = Tenant::factory()->create();
        $customerA = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $from = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerA->id, 'credit_balance' => 10]);
        $to = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerB->id, 'credit_balance' => 0]);

        $this->service->transfer($from, $to, 5);
    }

    // -----------------------------------------------------------
    // issueFromSubscription
    // -----------------------------------------------------------

    public function test_issue_from_subscription_creates_subscription_ledger_entry(): void
    {
        $dog = $this->makeDog(0);
        $package = Package::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'type' => 'subscription',
            'credit_count' => 15,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id' => $package->id,
            'dog_id' => $dog->id,
        ]);
        $periodEnd = now()->addMonth();

        $this->service->issueFromSubscription($subscription, $dog, $periodEnd);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('subscription', $entry->type);
        $this->assertSame(15, $entry->delta);
        $this->assertSame(15, $entry->balance_after);
        $this->assertSame($subscription->id, $entry->subscription_id);
        $this->assertNotNull($entry->expires_at);
        $this->assertEqualsWithDelta($periodEnd->timestamp, $entry->expires_at->timestamp, 2);
    }

    public function test_issue_from_subscription_increments_credit_balance(): void
    {
        $dog = $this->makeDog(5);
        $package = Package::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'type' => 'subscription',
            'credit_count' => 10,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id' => $package->id,
            'dog_id' => $dog->id,
        ]);

        $this->service->issueFromSubscription($subscription, $dog, now()->addMonth());

        $this->assertSame(15, $dog->fresh()->credit_balance);
    }

    public function test_issue_from_subscription_updates_credits_expire_at(): void
    {
        $dog = $this->makeDog(0);
        $package = Package::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'type' => 'subscription',
            'credit_count' => 10,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id' => $package->id,
            'dog_id' => $dog->id,
        ]);
        $periodEnd = now()->addMonth();

        $this->service->issueFromSubscription($subscription, $dog, $periodEnd);

        $fresh = $dog->fresh();
        $this->assertNotNull($fresh->credits_expire_at);
        $this->assertEqualsWithDelta($periodEnd->timestamp, $fresh->credits_expire_at->timestamp, 2);
    }

    // -----------------------------------------------------------
    // issueUnlimitedPassFromSubscription
    // -----------------------------------------------------------

    public function test_issue_unlimited_pass_from_subscription_creates_subscription_ledger_entry(): void
    {
        $dog = $this->makeDog(0);
        $package = Package::factory()->unlimited(30)->create([
            'tenant_id' => $dog->tenant_id,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'   => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id'  => $package->id,
            'dog_id'      => $dog->id,
        ]);
        $expiresAt = now()->addDays(30);

        $this->service->issueUnlimitedPassFromSubscription($subscription, $dog, $expiresAt);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('subscription', $entry->type);
        $this->assertSame(now()->daysInMonth, $entry->delta);
        $this->assertSame(now()->daysInMonth, $entry->balance_after);
        $this->assertSame($subscription->id, $entry->subscription_id);
        $this->assertNotNull($entry->expires_at);
    }

    public function test_issue_unlimited_pass_from_subscription_sets_unlimited_pass_expires_at(): void
    {
        $dog = $this->makeDog(0);
        $package = Package::factory()->unlimited(30)->create([
            'tenant_id' => $dog->tenant_id,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'   => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id'  => $package->id,
            'dog_id'      => $dog->id,
        ]);
        $expiresAt = now()->addDays(30);

        $this->service->issueUnlimitedPassFromSubscription($subscription, $dog, $expiresAt);

        $fresh = $dog->fresh();
        $this->assertSame(now()->daysInMonth, $fresh->credit_balance);
        $this->assertNotNull($fresh->unlimited_pass_expires_at);
        $this->assertEqualsWithDelta($expiresAt->timestamp, $fresh->unlimited_pass_expires_at->timestamp, 5);
    }

    public function test_issue_unlimited_pass_from_subscription_does_not_set_credits_expire_at(): void
    {
        $dog = $this->makeDog(0);
        $package = Package::factory()->unlimited(30)->create([
            'tenant_id' => $dog->tenant_id,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id'   => $dog->tenant_id,
            'customer_id' => $dog->customer_id,
            'package_id'  => $package->id,
            'dog_id'      => $dog->id,
        ]);

        $this->service->issueUnlimitedPassFromSubscription($subscription, $dog, now()->addDays(30));

        $this->assertNull($dog->fresh()->credits_expire_at);
    }

    // -----------------------------------------------------------
    // expireUnlimitedPass
    // -----------------------------------------------------------

    public function test_expire_unlimited_pass_removes_only_pass_credits(): void
    {
        // Dog has 3 one-time purchase credits + 31 pass credits = 34 total
        $dog = $this->makeDog(34);
        $passExpiresAt = now()->subMinute();
        $dog->update(['unlimited_pass_expires_at' => $passExpiresAt]);

        // Create the pass ledger entry with expires_at matching the dog's unlimited_pass_expires_at
        CreditLedger::allTenants()->newQuery()->insert([
            'id'             => \Illuminate\Support\Str::ulid(),
            'tenant_id'      => $dog->tenant_id,
            'dog_id'         => $dog->id,
            'type'           => 'subscription',
            'delta'          => 31,
            'balance_after'  => 34,
            'expires_at'     => $passExpiresAt,
            'created_at'     => now()->subDay(),
        ]);

        $this->service->expireUnlimitedPass($dog);

        $fresh = $dog->fresh();
        $this->assertSame(3, $fresh->credit_balance);
        $this->assertNull($fresh->unlimited_pass_expires_at);
    }

    public function test_expire_unlimited_pass_creates_expiry_removal_entry(): void
    {
        $dog = $this->makeDog(10);
        $passExpiresAt = now()->subMinute();
        $dog->update(['unlimited_pass_expires_at' => $passExpiresAt]);

        CreditLedger::allTenants()->newQuery()->insert([
            'id'             => \Illuminate\Support\Str::ulid(),
            'tenant_id'      => $dog->tenant_id,
            'dog_id'         => $dog->id,
            'type'           => 'subscription',
            'delta'          => 10,
            'balance_after'  => 10,
            'expires_at'     => $passExpiresAt,
            'created_at'     => now()->subDay(),
        ]);

        $this->service->expireUnlimitedPass($dog);

        $entry = CreditLedger::allTenants()->where('type', 'expiry_removal')->first();
        $this->assertNotNull($entry);
        $this->assertSame(-10, $entry->delta);
        $this->assertSame(0, $entry->balance_after);
    }

    public function test_expire_unlimited_pass_clears_flag_even_when_no_pass_credits_found(): void
    {
        $dog = $this->makeDog(5);
        $dog->update(['unlimited_pass_expires_at' => now()->subMinute()]);
        // No ledger entry with matching expires_at

        $this->service->expireUnlimitedPass($dog);

        $this->assertNull($dog->fresh()->unlimited_pass_expires_at);
        // Balance untouched
        $this->assertSame(5, $dog->fresh()->credit_balance);
    }

    // -----------------------------------------------------------
    // expireCredits
    // -----------------------------------------------------------

    public function test_expire_credits_zeros_balance(): void
    {
        $dog = $this->makeDog(6);

        $this->service->expireCredits($dog);

        $this->assertSame(0, $dog->fresh()->credit_balance);
    }

    public function test_expire_credits_creates_expiry_removal_entry(): void
    {
        $dog = $this->makeDog(6);

        $this->service->expireCredits($dog);

        $entry = CreditLedger::allTenants()->first();
        $this->assertSame('expiry_removal', $entry->type);
        $this->assertSame(-6, $entry->delta);
        $this->assertSame(0, $entry->balance_after);
    }

    public function test_expire_credits_does_nothing_when_balance_zero(): void
    {
        $dog = $this->makeDog(0);

        $this->service->expireCredits($dog);

        $this->assertDatabaseCount('credit_ledger', 0);
    }

    // -----------------------------------------------------------
    // dispatchCreditAlert — auto-replenish behaviour
    // -----------------------------------------------------------

    public function test_auto_replenish_job_dispatched_when_credits_hit_zero(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id'                 => $tenant->id,
            'customer_id'               => $customer->id,
            'credit_balance'            => 1,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->service->deductForAttendance($attendance);

        Queue::assertPushed(ProcessAutoReplenishJob::class, fn ($job) => $job->dogId === $dog->id);
    }

    public function test_auto_replenish_job_dispatched_even_when_low_alert_already_sent_today(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['low_credit_threshold' => 2]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id'                 => $tenant->id,
            'customer_id'               => $customer->id,
            'credit_balance'            => 1,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
            'credits_alert_sent_at'     => now()->subHour(), // alert sent recently
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->service->deductForAttendance($attendance);

        Queue::assertPushed(ProcessAutoReplenishJob::class, fn ($job) => $job->dogId === $dog->id);
    }

    public function test_no_credits_low_notification_when_auto_replenish_enabled(): void
    {
        Queue::fake();

        $notif = $this->mock(NotificationService::class);
        $notif->shouldNotReceive('enqueueGrouped');

        $tenant = Tenant::factory()->create(['low_credit_threshold' => 5]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer->update(['user_id' => $user->id]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id'                 => $tenant->id,
            'customer_id'               => $customer->id,
            'credit_balance'            => 3, // above zero but below threshold
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->service->deductForAttendance($attendance);

        // balance is now 2 (≤ threshold 5) — but no notification because auto-replenish handles it
        Queue::assertNotPushed(ProcessAutoReplenishJob::class); // balance > 0, no job
    }

    public function test_no_credits_empty_notification_when_auto_replenish_enabled(): void
    {
        Queue::fake();

        $notif = $this->mock(NotificationService::class);
        $notif->shouldNotReceive('enqueueGrouped');

        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer->update(['user_id' => $user->id]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id'                 => $tenant->id,
            'customer_id'               => $customer->id,
            'credit_balance'            => 1,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog->id,
        ]);

        $this->service->deductForAttendance($attendance);

        // balance hits 0 — job dispatched, but no credits.empty notification
        Queue::assertPushed(ProcessAutoReplenishJob::class);
    }
}
