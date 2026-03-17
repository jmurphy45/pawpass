<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.fake()->numerify('###');

        return [
            'name' => $name,
            'slug' => $slug,
            'owner_user_id' => null,
            'status' => 'active',
            'stripe_account_id' => null,
            'stripe_onboarded_at' => null,
            'platform_fee_pct' => '5.00',
            'payout_schedule' => 'monthly',
            'low_credit_threshold' => 2,
            'checkin_block_at_zero' => true,
            'timezone' => 'America/Chicago',
            'primary_color' => fake()->hexColor(),
        ];
    }

    public function withOwner(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $owner = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role' => 'business_owner',
                'status' => 'active',
            ]);
            $tenant->update(['owner_user_id' => $owner->id]);
        });
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending_verification']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
