<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BoardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'          => 'boardingportal',
            'status'        => 'active',
            'plan'          => 'pro',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://boardingportal.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_index_renders_boarding_page(): void
    {
        Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/my/boarding');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Boarding/Index')
                ->has('reservations')
            );
    }

    public function test_create_renders_booking_form_with_dogs(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $response = $this->actingAs($this->user)
            ->get('/my/boarding/create');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Boarding/Create')
                ->has('dogs')
            );
    }

    public function test_show_renders_reservation_detail(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/my/boarding/{$reservation->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Boarding/Show')
                ->has('reservation')
            );
    }

    public function test_show_returns_404_for_other_customers_reservation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'created_by'  => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/my/boarding/{$reservation->id}")
            ->assertStatus(404);
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get('/my/boarding')->assertRedirect('/my/login');
    }
}
