<?php

namespace Database\Factories;

use App\Models\BoardingReportCard;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardingReportCard>
 */
class BoardingReportCardFactory extends Factory
{
    protected $model = BoardingReportCard::class;

    public function definition(): array
    {
        $reservation = Reservation::factory();

        return [
            'tenant_id'      => fn (array $attrs) => Reservation::find($attrs['reservation_id'])?->tenant_id,
            'reservation_id' => $reservation,
            'report_date'    => now()->toDateString(),
            'notes'          => fake()->paragraph(),
            'created_by'     => User::factory()->state(['role' => 'staff']),
        ];
    }
}
