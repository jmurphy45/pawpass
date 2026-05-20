<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReservationAppointments extends Command
{
    protected $signature = 'appointments:backfill-reservations';

    protected $description = 'Create Appointment rows for reservations that predate the appointments table';

    public function handle(): int
    {
        $count = 0;

        Reservation::whereNull('appointment_id')
            ->with('tenant')
            ->chunkById(1000, function ($reservations) use (&$count) {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$count) {
                        $appointment = Appointment::create([
                            'tenant_id' => $reservation->tenant_id,
                            'dog_id' => $reservation->dog_id,
                            'customer_id' => $reservation->customer_id,
                            'service_type' => 'boarding',
                            'status' => $this->mapStatus($reservation->status),
                            'starts_at' => $reservation->starts_at,
                            'ends_at' => $reservation->ends_at,
                            'notes' => $reservation->notes,
                            'cancelled_at' => $reservation->cancelled_at,
                            'cancelled_by' => $reservation->cancelled_by,
                        ]);

                        $reservation->updateQuietly(['appointment_id' => $appointment->id]);
                        $count++;
                    });
                }
            });

        $this->info("Backfilled {$count} reservation(s).");

        return self::SUCCESS;
    }

    private function mapStatus(string $reservationStatus): string
    {
        return match ($reservationStatus) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'checked_in' => 'checked_in',
            'checked_out' => 'checked_out',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}
