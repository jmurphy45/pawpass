<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'dog_id' => Dog::factory()->state(['tenant_id' => $tenant]),
            'checked_in_by' => User::factory()->state(['tenant_id' => $tenant, 'role' => 'staff']),
            'checked_out_by' => null,
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'zero_credit_override' => false,
            'override_note' => null,
            'edited_by' => null,
            'edited_at' => null,
            'edit_note' => null,
            'original_in' => null,
            'original_out' => null,
        ];
    }

    public function checkedOut(): static
    {
        return $this->state([
            'checked_out_at' => now()->addHours(8),
            'checked_out_by' => User::factory()->state(['role' => 'staff']),
        ]);
    }

    public function withOverride(): static
    {
        return $this->state([
            'zero_credit_override' => true,
            'override_note' => 'Allowed by owner — owner will pay later',
        ]);
    }
}
