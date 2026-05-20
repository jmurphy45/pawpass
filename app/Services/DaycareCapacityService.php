<?php

namespace App\Services;

use App\Models\DaycareCapacityWindow;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DaycareCapacityService
{
    /**
     * Resolve effective capacity for a date using priority:
     *   one_time override → weekly recurring → tenant daily_dog_limit
     */
    public function getEffectiveCapacity(CarbonInterface $date): int
    {
        $tenantId = app('current.tenant.id');

        $oneTime = DaycareCapacityWindow::where('tenant_id', $tenantId)
            ->where('recurrence', 'one_time')
            ->where('specific_date', $date->toDateString())
            ->where('is_active', true)
            ->first();

        if ($oneTime) {
            return $oneTime->max_dogs;
        }

        $weekly = DaycareCapacityWindow::where('tenant_id', $tenantId)
            ->where('recurrence', 'weekly')
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if ($weekly) {
            return $weekly->max_dogs;
        }

        $limit = DB::table('tenants')->where('id', $tenantId)->value('daily_dog_limit');

        return (int) ($limit ?? 0);
    }

    public function countBooked(CarbonInterface $date): int
    {
        $tenantId = app('current.tenant.id');

        return DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('service_type', 'daycare_booking')
            ->whereNotIn('status', ['cancelled', 'no_show', 'checked_out'])
            ->whereNull('deleted_at')
            ->whereDate('starts_at', $date->toDateString())
            ->count();
    }

    public function isAvailable(CarbonInterface $date): bool
    {
        $capacity = $this->getEffectiveCapacity($date);

        if ($capacity <= 0) {
            return false;
        }

        return $this->countBooked($date) < $capacity;
    }
}
