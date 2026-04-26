<?php

namespace App\Services;

use App\Models\KennelUnit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class KennelAvailabilityService
{
    /**
     * Returns true if the kennel unit has no overlapping active reservations
     * for the given date range.
     *
     * Overlap condition (half-open interval intersection):
     *   existing.starts_at < requested.ends_at AND existing.ends_at > requested.starts_at
     *
     * Adjacent bookings (end of one == start of next) do NOT conflict.
     */
    public function isAvailable(
        KennelUnit $unit,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $excludeReservationId = null,
    ): bool {
        $query = $unit->reservations()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->whereRaw('COALESCE(actual_checkout_at, ends_at) > ?', [$startsAt]);

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->doesntExist();
    }

    /**
     * Returns all active units for a tenant that are available for a given date range.
     */
    public function availableUnits(
        string $tenantId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): Collection {
        return KennelUnit::where('is_active', true)
            ->whereDoesntHave('reservations', function ($q) use ($startsAt, $endsAt) {
                $q->where('status', '!=', 'cancelled')
                    ->where('starts_at', '<', $endsAt)
                    ->whereRaw('COALESCE(actual_checkout_at, ends_at) > ?', [$startsAt]);
            })
            ->orderBy('sort_order')
            ->get();
    }
}
