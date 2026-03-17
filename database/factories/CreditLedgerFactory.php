<?php

namespace Database\Factories;

use App\Models\CreditLedger;
use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditLedger>
 */
class CreditLedgerFactory extends Factory
{
    protected $model = CreditLedger::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        $delta = fake()->randomElement([5, 10, 20]);

        return [
            'tenant_id' => $tenant,
            'dog_id' => Dog::factory()->state(['tenant_id' => $tenant]),
            'type' => 'purchase',
            'delta' => $delta,
            'balance_after' => $delta,
            'expires_at' => null,
            'order_id' => null,
            'attendance_id' => null,
            'subscription_id' => null,
            'parent_ledger_id' => null,
            'created_by' => null,
            'note' => null,
        ];
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deduction',
            'delta' => -1,
            'balance_after' => max(0, $attributes['balance_after'] - 1),
        ]);
    }

    public function goodwill(string $note = 'Goodwill credit'): static
    {
        return $this->state([
            'type' => 'goodwill',
            'delta' => 1,
            'note' => $note,
        ]);
    }
}
