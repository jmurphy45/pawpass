<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DogCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'               => 'testco',
            'status'             => 'active',
            'plan'               => 'starter',
            'checkin_block_at_zero' => false,
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();
    }

    public function test_index_shows_roster(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['name' => 'Buddy', 'credit_balance' => 5]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/roster');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->has('roster', 1)
            ->where('roster.0.name', 'Buddy')
            ->where('roster.0.attendance_state', 'not_in')
        );
    }

    public function test_checkin_creates_attendance_record(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
    }

    public function test_checkout_sets_checked_out_at(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $attendance = Attendance::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'dog_id'       => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNotNull($attendance->fresh()->checked_out_at);
    }

    public function test_checkout_does_not_close_previous_day_open_attendance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $this->staff->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull(Attendance::where('dog_id', $dog->id)->first()->checked_out_at);
    }

    public function test_checkout_closes_todays_record_not_previous_day(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $oldAttendance = Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $this->staff->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $todayAttendance = Attendance::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $this->staff->id,
            'checked_in_at'  => now(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNull($oldAttendance->fresh()->checked_out_at);
        $this->assertNotNull($todayAttendance->fresh()->checked_out_at);
    }

    public function test_cannot_checkin_dog_with_zero_credits_when_blocked(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
    }
}
