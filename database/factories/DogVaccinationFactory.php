<?php

namespace Database\Factories;

use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DogVaccination>
 */
class DogVaccinationFactory extends Factory
{
    protected $model = DogVaccination::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id'       => $tenant,
            'dog_id'          => Dog::factory()->state(['tenant_id' => $tenant]),
            'vaccine_name'    => fake()->randomElement(['Rabies', 'Bordetella', 'DHPP', 'Leptospirosis', 'Canine Influenza']),
            'administered_at' => now()->subMonths(fake()->numberBetween(1, 11))->toDateString(),
            'expires_at'      => now()->addYear()->toDateString(),
            'administered_by' => fake()->optional()->company(),
            'notes'           => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'administered_at' => now()->subYears(2)->toDateString(),
            'expires_at'      => now()->subMonth()->toDateString(),
        ]);
    }

    public function noExpiry(): static
    {
        return $this->state(['expires_at' => null]);
    }

    public function expiringSoon(): static
    {
        return $this->state([
            'administered_at' => now()->subMonths(11)->toDateString(),
            'expires_at'      => now()->addDays(20)->toDateString(),
            'warning_sent_at' => null,
            'urgent_sent_at'  => null,
        ]);
    }

    public function expiringUrgent(): static
    {
        return $this->state([
            'administered_at' => now()->subMonths(11)->toDateString(),
            'expires_at'      => now()->addDays(5)->toDateString(),
            'warning_sent_at' => null,
            'urgent_sent_at'  => null,
        ]);
    }

    public function warningSent(): static
    {
        return $this->state(['warning_sent_at' => now()->subDay()]);
    }

    public function urgentSent(): static
    {
        return $this->state(['urgent_sent_at' => now()->subDay()]);
    }
}
