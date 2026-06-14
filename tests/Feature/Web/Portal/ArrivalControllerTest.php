<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\ParkingSpot;
use App\Models\PlatformPlan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ArrivalControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $customerUser;

    private Customer $customer;

    private Dog $dog;

    private ParkingSpot $spot;

    private User $staffUser;

    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['parking_management', 'boarding']]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'pawco',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        URL::forceRootUrl('http://pawco.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $this->customer->update(['user_id' => $this->customerUser->id]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->create();

        $this->spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'A1',
            'name' => 'Spot A1',
            'is_active' => true,
        ]);

        $this->staffUser = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->ownerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    // --- show() ---

    public function test_show_renders_arrive_page_with_todays_confirmed_reservations(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $response = $this->actingAs($this->customerUser)
            ->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Arrive')
                ->where('spot.id', $this->spot->id)
                ->where('spot.spot_number', 'A1')
                ->has('reservations', 1)
                ->where('reservations.0.id', $reservation->id)
            );
    }

    public function test_show_excludes_non_confirmed_reservations(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $response = $this->actingAs($this->customerUser)
            ->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Arrive')
                ->has('reservations', 0)
            );
    }

    public function test_show_excludes_reservations_not_starting_today(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDays(3)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $response = $this->actingAs($this->customerUser)
            ->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('reservations', 0)
            );
    }

    public function test_show_returns_404_when_feature_inactive(): void
    {
        $this->tenant->update(['plan' => 'free']);

        $this->actingAs($this->customerUser)
            ->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}")
            ->assertNotFound();
    }

    public function test_show_returns_404_for_inactive_spot(): void
    {
        $this->spot->update(['is_active' => false]);

        $this->actingAs($this->customerUser)
            ->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}")
            ->assertNotFound();
    }

    public function test_show_redirects_unauthenticated_to_login(): void
    {
        $this->get("/my/arrive/{$this->tenant->id}/{$this->spot->id}")
            ->assertRedirect();
    }

    // --- store() ---

    public function test_customer_can_announce_arrival_for_confirmed_reservation(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertNotNull($reservation->arrived_at);
        $this->assertSame($this->spot->id, $reservation->parking_spot_id);
    }

    public function test_store_sends_notification_to_staff_and_owner(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ]);

        Notification::assertSentTo(
            $this->staffUser,
            PawPassNotification::class,
            fn ($n) => $n->type === 'boarding.curbside_arrival'
        );

        Notification::assertSentTo(
            $this->ownerUser,
            PawPassNotification::class,
            fn ($n) => $n->type === 'boarding.curbside_arrival'
        );
    }

    public function test_store_does_not_send_notification_to_customer_user(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ]);

        Notification::assertNotSentTo(
            $this->customerUser,
            PawPassNotification::class
        );
    }

    public function test_store_rejects_non_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_reservation_not_starting_today(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDays(3)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_already_announced_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
            'arrived_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_other_customers_reservation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertForbidden();
    }

    public function test_store_requires_parking_management_feature(): void
    {
        $this->tenant->update(['plan' => 'free']);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $this->spot->spot_number,
            ])
            ->assertForbidden();
    }

    public function test_store_rejects_parking_spot_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['plan' => 'starter']);
        $otherSpot = ParkingSpot::factory()->create([
            'tenant_id' => $otherTenant->id,
            'spot_number' => 'B1',
            'is_active' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(2)->startOfDay(),
            'created_by' => $this->staffUser->id,
        ]);

        $this->actingAs($this->customerUser)
            ->post("/my/boarding/{$reservation->id}/arrive", [
                'spot_number' => $otherSpot->spot_number,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('spot_number');
    }
}
