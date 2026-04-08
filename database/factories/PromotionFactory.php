<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => Tenant::factory(),
            'name'               => fake()->words(3, true),
            'code'               => strtoupper(Str::random(8)),
            'type'               => 'percentage',
            'discount_value'     => fake()->numberBetween(5, 30),
            'applicable_type'    => null,
            'applicable_id'      => null,
            'min_purchase_cents' => 0,
            'expires_at'         => null,
            'max_uses'           => null,
            'used_count'         => 0,
            'is_active'          => true,
            'description'        => null,
            'created_by'         => null,
        ];
    }

    public function percentage(int $pct = 10): static
    {
        return $this->state(['type' => 'percentage', 'discount_value' => $pct]);
    }

    public function fixedCents(int $cents = 500): static
    {
        return $this->state(['type' => 'fixed_cents', 'discount_value' => $cents]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
