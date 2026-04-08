<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantEvent>
 */
class TenantEventFactory extends Factory
{
    protected $model = TenantEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'event_type' => fake()->randomElement(['first_checkin', 'first_purchase', 'onboarded', 'plan_upgraded']),
            'payload' => null,
        ];
    }

    public function forEvent(string $eventType, array $payload = []): static
    {
        return $this->state([
            'event_type' => $eventType,
            'payload' => empty($payload) ? null : $payload,
        ]);
    }
}
