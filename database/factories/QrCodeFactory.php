<?php

namespace Database\Factories;

use App\Models\QrCode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'token' => (string) fake()->unique()->numerify('##################'),
            'key' => fake()->unique()->slug(2),
            'target_url' => '/my',
            'label' => fake()->words(2, true),
            'is_active' => true,
            'scan_count' => 0,
        ];
    }
}
