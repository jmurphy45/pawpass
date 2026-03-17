<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'customer_id' => Customer::factory()->state(['tenant_id' => $tenant]),
            'package_id' => Package::factory()->subscription()->state(['tenant_id' => $tenant]),
            'dog_id' => Dog::factory()->state(['tenant_id' => $tenant]),
            'status' => 'active',
            'stripe_sub_id' => 'sub_'.Str::random(24),
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'cancelled_at' => null,
        ];
    }

    public function pastDue(): static
    {
        return $this->state(['status' => 'past_due']);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
