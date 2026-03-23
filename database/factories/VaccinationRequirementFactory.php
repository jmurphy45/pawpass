<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VaccinationRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VaccinationRequirement>
 */
class VaccinationRequirementFactory extends Factory
{
    protected $model = VaccinationRequirement::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => Tenant::factory(),
            'vaccine_name' => fake()->unique()->randomElement(['Rabies', 'Bordetella', 'DHPP', 'Leptospirosis', 'DA2PP', 'Parainfluenza', 'Lyme']),
        ];
    }
}
