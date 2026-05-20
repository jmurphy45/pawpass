<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DaycareCapacityWindow;
use App\Models\Dog;
use App\Models\Tenant;
use App\Services\DaycareCapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaycareCapacityServiceTest extends TestCase
{
    use RefreshDatabase;

    private DaycareCapacityService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['daily_dog_limit' => 10, 'status' => 'active']);
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->service = app(DaycareCapacityService::class);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_falls_back_to_tenant_daily_dog_limit(): void
    {
        $capacity = $this->service->getEffectiveCapacity(now());

        $this->assertSame(10, $capacity);
    }

    public function test_weekly_window_overrides_tenant_limit(): void
    {
        DaycareCapacityWindow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurrence' => 'weekly',
            'day_of_week' => now()->dayOfWeek,
            'specific_date' => null,
            'max_dogs' => 5,
            'is_active' => true,
        ]);

        $this->assertSame(5, $this->service->getEffectiveCapacity(now()));
    }

    public function test_one_time_window_overrides_weekly(): void
    {
        DaycareCapacityWindow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurrence' => 'weekly',
            'day_of_week' => now()->dayOfWeek,
            'specific_date' => null,
            'max_dogs' => 5,
            'is_active' => true,
        ]);

        DaycareCapacityWindow::factory()->oneTime(now())->create([
            'tenant_id' => $this->tenant->id,
            'max_dogs' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(2, $this->service->getEffectiveCapacity(now()));
    }

    public function test_inactive_window_is_ignored(): void
    {
        DaycareCapacityWindow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurrence' => 'weekly',
            'day_of_week' => now()->dayOfWeek,
            'specific_date' => null,
            'max_dogs' => 3,
            'is_active' => false,
        ]);

        $this->assertSame(10, $this->service->getEffectiveCapacity(now()));
    }

    public function test_count_booked_counts_non_cancelled_daycare_booking_appointments(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'daycare_booking',
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(17),
        ]);

        $this->assertSame(1, $this->service->countBooked(now()));
    }

    public function test_count_booked_excludes_cancelled(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Appointment::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'daycare_booking',
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(17),
        ]);

        $this->assertSame(0, $this->service->countBooked(now()));
    }

    public function test_count_booked_excludes_other_service_types(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'vet',
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(17),
        ]);

        $this->assertSame(0, $this->service->countBooked(now()));
    }

    public function test_is_available_returns_true_when_under_capacity(): void
    {
        $this->assertTrue($this->service->isAvailable(now()));
    }

    public function test_is_available_returns_false_when_at_capacity(): void
    {
        $this->tenant->update(['daily_dog_limit' => 1]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'customer_id' => $customer->id,
            'service_type' => 'daycare_booking',
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(17),
        ]);

        $this->assertFalse($this->service->isAvailable(now()));
    }

    public function test_is_available_returns_false_when_limit_is_zero(): void
    {
        $this->tenant->update(['daily_dog_limit' => 0]);

        $this->assertFalse($this->service->isAvailable(now()));
    }
}
