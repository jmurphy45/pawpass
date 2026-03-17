<?php

namespace Database\Factories;

use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPlan>
 */
class PlatformPlanFactory extends Factory
{
    protected $model = PlatformPlan::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug'                    => $slug,
            'name'                    => ucfirst($slug),
            'description'             => fake()->sentence(),
            'monthly_price_cents'     => fake()->numberBetween(1000, 50000),
            'annual_price_cents'      => fake()->numberBetween(10000, 500000),
            'stripe_product_id'       => null,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id'  => null,
            'features'                => [],
            'staff_limit'             => 1,
            'is_active'               => true,
            'sort_order'              => 0,
        ];
    }

    public function synced(): static
    {
        return $this->state([
            'stripe_product_id'       => 'prod_'.fake()->bothify('??????????'),
            'stripe_monthly_price_id' => 'price_'.fake()->bothify('??????????'),
            'stripe_annual_price_id'  => 'price_'.fake()->bothify('??????????'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
