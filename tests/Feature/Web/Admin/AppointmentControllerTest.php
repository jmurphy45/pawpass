<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => []]);
        $this->tenant = Tenant::factory()->create(['slug' => 'appt-web', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://appt-web.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->staff = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_index_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Appointments/Index'));
    }

    public function test_index_returns_appointments_prop(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Appointments/Index')
            ->has('appointments.data', 1)
        );
    }

    public function test_index_filters_by_service_type(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
            'starts_at' => now()->addDay(),
        ]);

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments?service_type=vet');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('appointments.data', 1));
    }

    public function test_index_filters_by_status(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        Appointment::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments?status=pending');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('appointments.data', 1));
    }

    public function test_index_filters_by_date(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDays(5),
        ]);

        $this->actingAs($this->staff);

        $date = now()->addDay()->toDateString();
        $response = $this->get("/admin/appointments?date={$date}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('appointments.data', 1));
    }

    public function test_index_scopes_to_current_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-appt', 'status' => 'active', 'plan' => 'starter']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDog = Dog::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $otherCustomer->id]);

        Appointment::factory()->pending()->create([
            'tenant_id' => $otherTenant->id,
            'dog_id' => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'service_type' => 'grooming',
            'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('appointments.data', 0));
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->get('/admin/appointments');

        $response->assertRedirect();
    }

    public function test_calendar_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments/calendar');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Appointments/Calendar'));
    }

    public function test_calendar_returns_appointments_in_week(): void
    {
        $weekStart = now()->startOfWeek(\Carbon\Carbon::SUNDAY);

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
            'starts_at' => $weekStart->copy()->addDays(2)->setTime(10, 0),
        ]);

        // outside this week
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
            'starts_at' => $weekStart->copy()->addDays(10)->setTime(10, 0),
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/appointments/calendar');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Appointments/Calendar')
            ->has('appointments', 1)
        );
    }

    public function test_calendar_accepts_week_parameter(): void
    {
        $week = '2026-06-01';

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'grooming',
            'starts_at' => '2026-06-02 09:00:00',
        ]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/appointments/calendar?week={$week}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Appointments/Calendar')
            ->has('appointments', 1)
            ->where('weekStart', '2026-05-31')
        );
    }
}
