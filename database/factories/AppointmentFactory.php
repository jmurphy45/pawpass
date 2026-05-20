<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        $customer = Customer::factory()->state(['tenant_id' => $tenant]);
        $dog = Dog::factory()->state(['tenant_id' => $tenant, 'customer_id' => $customer]);

        return [
            'tenant_id' => $tenant,
            'dog_id' => $dog,
            'customer_id' => $customer,
            'service_type' => 'vet',
            'status' => 'draft',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'notes' => null,
            'price_cents' => null,
            'resource_id' => null,
            'assigned_user_id' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(['status' => 'no_show']);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
        ]);
    }
}
