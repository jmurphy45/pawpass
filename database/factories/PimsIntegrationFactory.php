<?php

namespace Database\Factories;

use App\Models\PimsIntegration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PimsIntegration>
 */
class PimsIntegrationFactory extends Factory
{
    protected $model = PimsIntegration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'provider' => 'ezyvet',
            'api_base_url' => 'https://demo.ezyvet.com/api/v1',
            'credentials' => [
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'access_token' => null,
                'token_expires_at' => null,
            ],
            'status' => 'active',
        ];
    }

    public function vetspire(): static
    {
        return $this->state([
            'provider' => 'vetspire',
            'api_base_url' => null,
            'credentials' => [
                'access_token' => fake()->sha256(),
            ],
        ]);
    }

    public function errored(): static
    {
        return $this->state([
            'status' => 'error',
            'sync_error' => 'Connection refused',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
