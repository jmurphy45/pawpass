<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AddonType;
use App\Models\BoardingReportCard;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VaccinationRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BoardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'boarding-web', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://boarding-web.pawpass.com');

        $this->staff    = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog      = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_reservations_index_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/reservations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/Reservations'));
    }

    public function test_reservations_index_contains_reservations_prop(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/reservations');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/Reservations')
            ->has('reservations')
        );
    }

    public function test_reservation_show_renders_inertia_page(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/boarding/reservations/{$reservation->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/ReservationShow'));
    }

    public function test_reservation_show_contains_required_props(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        DogVaccination::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'vaccine_name' => 'Rabies', 'administered_at' => '2026-01-01', 'expires_at' => '2027-01-01',
        ]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/boarding/reservations/{$reservation->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/ReservationShow')
            ->has('reservation')
            ->has('reportCards')
            ->has('addons')
            ->has('addonTypes')
            ->has('vaccinationCompliance')
        );
    }

    public function test_occupancy_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/occupancy');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/Occupancy'));
    }

    public function test_occupancy_contains_units_and_range_props(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/occupancy?from=2026-05-01&to=2026-05-07');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/Occupancy')
            ->has('units')
            ->has('from')
            ->has('to')
        );
    }
}
