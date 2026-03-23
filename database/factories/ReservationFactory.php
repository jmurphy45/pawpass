<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        $customer = Customer::factory()->state(['tenant_id' => $tenant]);
        $dog = Dog::factory()->state(['tenant_id' => $tenant, 'customer_id' => $customer]);

        return [
            'tenant_id'          => $tenant,
            'dog_id'             => $dog,
            'customer_id'        => $customer,
            'kennel_unit_id'     => null,
            'status'             => 'pending',
            'starts_at'          => now()->addDay()->startOfDay(),
            'ends_at'            => now()->addDays(3)->startOfDay(),
            'nightly_rate_cents' => 5000,
            'notes'              => null,
            'created_by'         => User::factory()->state(['tenant_id' => $tenant, 'role' => 'staff']),
            'cancelled_at'       => null,
            'cancelled_by'       => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function checkedIn(): static
    {
        return $this->state(['status' => 'checked_in']);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function withUnit(KennelUnit $unit): static
    {
        return $this->state(['kennel_unit_id' => $unit->id]);
    }
}
