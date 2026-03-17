<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'customer_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
            'phone' => null,
            'timezone' => null,
            'invite_token' => null,
            'invite_expires_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function businessOwner(): static
    {
        return $this->state(['role' => 'business_owner']);
    }

    public function staff(): static
    {
        return $this->state(['role' => 'staff']);
    }

    public function platformAdmin(): static
    {
        return $this->state(['role' => 'platform_admin', 'tenant_id' => null]);
    }

    public function pendingInvite(): static
    {
        return $this->state([
            'status' => 'pending_invite',
            'invite_token' => Str::random(64),
            'invite_expires_at' => now()->addHours(48),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
