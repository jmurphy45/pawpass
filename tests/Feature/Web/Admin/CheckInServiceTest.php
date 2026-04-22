<?php

namespace Tests\Feature\Web\Admin;

use App\Exceptions\CheckInException;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AutoReplenishService;
use App\Services\CheckInService;
use App\Services\DogCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'testco',
            'status' => 'active',
            'plan' => 'starter',
            'checkin_block_at_zero' => false,
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();
    }

    private function service(): CheckInService
    {
        return $this->app->make(CheckInService::class);
    }

    public function test_ineligible_dog_throws(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 5,
            'status' => 'inactive',
        ]);

        $this->expectException(CheckInException::class);
        $this->expectExceptionMessage('cannot be checked in');

        $this->service()->execute($dog, $this->staff, $this->tenant, false, null);
    }

    public function test_already_checked_in_today_throws(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5, 'status' => 'active']);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->expectException(CheckInException::class);
        $this->expectExceptionMessage('already checked in today');

        $this->service()->execute($dog, $this->staff, $this->tenant, false, null);
    }

    public function test_zero_credits_with_hard_block_and_no_override_throws(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0, 'status' => 'active']);

        $this->expectException(CheckInException::class);
        $this->expectExceptionMessage('Cannot check in dog with zero credits');

        $this->service()->execute($dog, $this->staff, $this->tenant->fresh(), false, null);
    }

    public function test_zero_credits_with_hard_block_but_manual_override_succeeds(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0, 'status' => 'active']);

        $attendance = $this->service()->execute($dog, $this->staff, $this->tenant->fresh(), true, 'owner approved');

        $this->assertDatabaseHas('attendances', [
            'dog_id' => $dog->id,
            'zero_credit_override' => true,
            'override_note' => 'owner approved',
        ]);
        $this->assertInstanceOf(Attendance::class, $attendance);
    }

    public function test_zero_credits_soft_policy_no_auto_replenish_creates_attendance_with_override(): void
    {
        // checkin_block_at_zero = false (setUp default) — soft policy, proceed with override
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0, 'status' => 'active']);

        $attendance = $this->service()->execute($dog, $this->staff, $this->tenant, false, null);

        $this->assertDatabaseHas('attendances', [
            'dog_id' => $dog->id,
            'zero_credit_override' => true,
        ]);
        $this->assertInstanceOf(Attendance::class, $attendance);
    }

    public function test_auto_replenish_no_card_throws_and_leaves_no_attendance(): void
    {
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => null,
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'status' => 'active',
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->expectException(CheckInException::class);
        $this->expectExceptionMessage('no card on file');

        try {
            $this->service()->execute($dog, $this->staff, $this->tenant, false, null);
        } finally {
            $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
        }
    }

    public function test_auto_replenish_charge_failure_throws_and_leaves_no_attendance(): void
    {
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'status' => 'active',
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturn(false);

        $this->expectException(CheckInException::class);
        $this->expectExceptionMessage('Auto-replenish charge failed');

        try {
            $this->service()->execute($dog, $this->staff, $this->tenant, false, null);
        } finally {
            $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
        }
    }

    public function test_auto_replenish_success_creates_attendance(): void
    {
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'status' => 'active',
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturn(true);

        $attendance = $this->service()->execute($dog, $this->staff, $this->tenant, false, null);

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
        $this->assertInstanceOf(Attendance::class, $attendance);
    }

    public function test_unlimited_pass_skips_deduction_and_creates_attendance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'status' => 'active',
            'unlimited_pass_expires_at' => now()->addDays(30),
        ]);

        $attendance = $this->service()->execute($dog, $this->staff, $this->tenant, false, null);

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
        $this->assertInstanceOf(Attendance::class, $attendance);
    }

    public function test_normal_checkin_with_credits_creates_attendance(): void
    {
        // Use hard block so override stays false when credits are present
        $this->tenant->update(['checkin_block_at_zero' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5, 'status' => 'active']);

        $attendance = $this->service()->execute($dog, $this->staff, $this->tenant->fresh(), false, null);

        $this->assertDatabaseHas('attendances', [
            'dog_id' => $dog->id,
            'zero_credit_override' => false,
        ]);
        $this->assertInstanceOf(Attendance::class, $attendance);
    }
}
