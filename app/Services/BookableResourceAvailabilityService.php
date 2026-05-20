<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BookableResource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class BookableResourceAvailabilityService
{
    public function isAvailable(BookableResource $resource, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $excludeAppointmentId = null): bool
    {
        return ! $this->overlapQuery($resource, $startsAt, $endsAt, $excludeAppointmentId)->exists();
    }

    public function getConflicts(BookableResource $resource, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $excludeAppointmentId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->overlapQuery($resource, $startsAt, $endsAt, $excludeAppointmentId)->get();
    }

    private function overlapQuery(BookableResource $resource, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $excludeAppointmentId): Builder
    {
        $query = Appointment::query()
            ->where('resource_id', $resource->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query;
    }
}
