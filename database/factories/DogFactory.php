<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dog>
 */
class DogFactory extends Factory
{
    protected $model = Dog::class;

    private static array $dogNames = [
        'Buddy', 'Max', 'Charlie', 'Cooper', 'Rocky', 'Milo', 'Bear', 'Duke',
        'Sadie', 'Bella', 'Daisy', 'Lucy', 'Molly', 'Maggie', 'Luna', 'Lola',
    ];

    private static array $breeds = [
        'Labrador Retriever', 'Golden Retriever', 'German Shepherd', 'Bulldog',
        'Poodle', 'Beagle', 'Rottweiler', 'Yorkshire Terrier', 'Dachshund',
        'Boxer', 'Shih Tzu', 'Siberian Husky', 'Australian Shepherd',
    ];

    public function definition(): array
    {
        $customer = Customer::factory();

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => $customer,
            'name' => fake()->randomElement(self::$dogNames),
            'breed' => fake()->optional()->randomElement(self::$breeds),
            'dob' => fake()->boolean(70) ? fake()->dateTimeBetween('-15 years', '-6 months')->format('Y-m-d') : null,
            'sex' => fake()->optional()->randomElement(['male', 'female', 'unknown']),
            'photo_url' => null,
            'vet_name' => fake()->optional()->company(),
            'vet_phone' => fake()->optional()->phoneNumber(),
            'credit_balance' => fake()->numberBetween(0, 20),
            'credits_expire_at' => null,
            'credits_alert_sent_at' => null,
            'auto_replenish_enabled' => false,
            'auto_replenish_package_id' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
        ]);
    }

    public function withCredits(int $credits): static
    {
        return $this->state(['credit_balance' => $credits]);
    }

    public function noCredits(): static
    {
        return $this->state(['credit_balance' => 0]);
    }
}
