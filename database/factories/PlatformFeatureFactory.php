<?php

namespace Database\Factories;

use App\Models\PlatformFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformFeature>
 */
class PlatformFeatureFactory extends Factory
{
    protected $model = PlatformFeature::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug'         => $slug,
            'name'         => ucwords(str_replace('-', ' ', $slug)),
            'description'  => fake()->sentence(),
            'is_marketing' => true,
            'sort_order'   => 0,
        ];
    }
}
