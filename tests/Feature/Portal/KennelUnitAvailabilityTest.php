<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class KennelUnitAvailabilityTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'pro', 'features' => ['boarding']]);

        $this->tenant = Tenant::factory()->create([
            'slug'          => 'kennelunits',
            'status'        => 'active',
            'plan'          => 'pro',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://kennelunits.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    public function test_returns_available_units_for_date_range(): void
    {
        $available = KennelUnit::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $booked = KennelUnit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        Reservation::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'kennel_unit_id' => $booked->id,
            'starts_at'      => now()->addDay(),
            'ends_at'        => now()->addDays(5),
            'created_by'     => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/kennel-units/available?starts_at='.now()->addDays(2)->toDateString().'&ends_at='.now()->addDays(4)->toDateString());

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($booked->id, $ids);
    }

    public function test_inactive_units_excluded(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/kennel-units/available?starts_at='.now()->addDay()->toDateString().'&ends_at='.now()->addDays(3)->toDateString());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_returns_422_when_dates_missing(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/kennel-units/available')
            ->assertStatus(422);
    }

    public function test_daycare_tenant_gets_403(): void
    {
        $daycareTenant = Tenant::factory()->create([
            'slug'          => 'daycareonlytest',
            'status'        => 'active',
            'business_type' => 'daycare',
        ]);
        URL::forceRootUrl('http://daycareonlytest.pawpass.com');

        $customer = Customer::factory()->create(['tenant_id' => $daycareTenant->id]);
        $user = User::factory()->create([
            'tenant_id'   => $daycareTenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
        ]);
        $customer->update(['user_id' => $user->id]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->jwtFor($user)])
            ->getJson('/api/portal/v1/kennel-units/available?starts_at='.now()->addDay()->toDateString().'&ends_at='.now()->addDays(3)->toDateString())
            ->assertStatus(403);
    }
}
