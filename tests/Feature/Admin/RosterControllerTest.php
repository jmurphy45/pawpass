<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class RosterControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'rostertest',
            'status' => 'active',
            'low_credit_threshold' => 2,
            'checkin_block_at_zero' => true,
        ]);
        URL::forceRootUrl('http://rostertest.pawpass.com');

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'staff',
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    private function makeDog(int $credits = 5): Dog
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        return Dog::factory()->forCustomer($customer)->create(['credit_balance' => $credits]);
    }

    public function test_roster_lists_dogs_with_credit_status_and_attendance_state(): void
    {
        $ready = $this->makeDog(10);
        $low = $this->makeDog(1);
        $empty = $this->makeDog(0);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/roster');

        $response->assertStatus(200);

        $data = collect($response->json('data'));

        $this->assertEquals('ready', $data->firstWhere('id', $ready->id)['credit_status']);
        $this->assertEquals('low', $data->firstWhere('id', $low->id)['credit_status']);
        $this->assertEquals('empty', $data->firstWhere('id', $empty->id)['credit_status']);

        foreach ($data as $row) {
            $this->assertEquals('not_in', $row['attendance_state']);
        }
    }

    public function test_roster_shows_checked_in_state(): void
    {
        $dog = $this->makeDog(5);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/roster');

        $data = collect($response->json('data'));
        $this->assertEquals('checked_in', $data->firstWhere('id', $dog->id)['attendance_state']);
    }

    public function test_roster_shows_done_state(): void
    {
        $dog = $this->makeDog(5);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => now(),
            'checked_out_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/roster');

        $data = collect($response->json('data'));
        $this->assertEquals('done', $data->firstWhere('id', $dog->id)['attendance_state']);
    }

    public function test_normal_checkin_creates_attendance_and_deducts_credit(): void
    {
        $dog = $this->makeDog(5);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'checked_in');

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
        $this->assertEquals(4, $dog->fresh()->credit_balance);
    }

    public function test_zero_credit_blocked_when_block_enabled(): void
    {
        $dog = $this->makeDog(0);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.0.error_code', 'ZERO_CREDITS_BLOCKED');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_zero_credit_override_allows_checkin(): void
    {
        $dog = $this->makeDog(0);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [[
                    'dog_id' => $dog->id,
                    'zero_credit_override' => true,
                    'override_note' => 'Manager approved',
                ]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'checked_in');

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id, 'zero_credit_override' => true]);
    }

    public function test_already_checked_in_returns_409_error_code(): void
    {
        $dog = $this->makeDog(5);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.0.error_code', 'DOG_ALREADY_CHECKED_IN');
    }

    public function test_bulk_checkin_processes_multiple_dogs(): void
    {
        $dog1 = $this->makeDog(5);
        $dog2 = $this->makeDog(3);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [
                    ['dog_id' => $dog1->id],
                    ['dog_id' => $dog2->id],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('checked_in', $response->json('data.0.status'));
        $this->assertEquals('checked_in', $response->json('data.1.status'));
    }

    public function test_checkout_sets_checked_out_at(): void
    {
        $dog = $this->makeDog(5);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkout', [
                'dog_id' => $dog->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.dog_id', $dog->id);

        $attendance = Attendance::where('dog_id', $dog->id)->first();
        $this->assertNotNull($attendance->checked_out_at);
    }

    public function test_checkout_returns_404_when_no_open_attendance(): void
    {
        $dog = $this->makeDog(5);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkout', [
                'dog_id' => $dog->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_checkout_does_not_close_previous_day_open_attendance(): void
    {
        $dog = $this->makeDog(5);

        Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkout', [
                'dog_id' => $dog->id,
            ]);

        $response->assertStatus(404);

        $this->assertNull(Attendance::where('dog_id', $dog->id)->first()->checked_out_at);
    }

    public function test_checkout_closes_todays_record_not_previous_day(): void
    {
        $dog = $this->makeDog(5);

        $oldAttendance = Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $todayAttendance = Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now(),
            'checked_out_at' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkout', [
                'dog_id' => $dog->id,
            ]);

        $response->assertStatus(200);

        $this->assertNull($oldAttendance->fresh()->checked_out_at);
        $this->assertNotNull($todayAttendance->fresh()->checked_out_at);
    }

    public function test_dog_with_active_unlimited_pass_checks_in_without_credits(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'unlimited_pass_expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'checked_in');

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
        $this->assertSame(0, $dog->fresh()->credit_balance);
    }

    public function test_dog_with_expired_unlimited_pass_is_blocked_at_zero_credits(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'unlimited_pass_expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.0.error_code', 'ZERO_CREDITS_BLOCKED');
    }

    public function test_auto_replenish_charges_and_checks_in_when_blocking_disabled(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => false]);

        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturnUsing(function () use ($dog) {
                $dog->increment('credit_balance', 5);

                return true;
            });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'checked_in');

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
    }

    public function test_auto_replenish_failure_blocks_checkin(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => false]);

        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturn(false);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.0.error_code', 'AUTO_REPLENISH_FAILED');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_auto_replenish_skipped_when_blocking_enabled(): void
    {
        // blocking is true by default in this test class setUp
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $autoReplenish = $this->mock(AutoReplenishService::class);
        $autoReplenish->shouldNotReceive('triggerSync');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/roster/checkin', [
                'dogs' => [['dog_id' => $dog->id]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'error')
            ->assertJsonPath('data.0.error_code', 'ZERO_CREDITS_BLOCKED');
    }
}
