<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendancePaymentService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class AlertStaleCheckins implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService, AttendancePaymentService $attendancePayments): void
    {
        $stale = Attendance::allTenants()
            ->whereNull('checked_out_at')
            ->whereDate('checked_in_at', '<', today())
            ->with(['dog'])
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        $byTenant = $stale->groupBy('tenant_id');

        foreach ($byTenant as $tenantId => $records) {
            try {
                $tenant = Tenant::find($tenantId);
                if (! $tenant) {
                    continue;
                }

                if ($tenant->auto_checkout_stale) {
                    $this->autoCheckout($tenant, $records, $attendancePayments);
                } else {
                    $this->notifyStaff($tenant, $records, $notificationService);
                }
            } catch (\Throwable $e) {
                Log::error('AlertStaleCheckins failed for tenant', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    private function autoCheckout(Tenant $tenant, $records, AttendancePaymentService $attendancePayments): void
    {
        foreach ($records as $attendance) {
            $endOfDay = $attendance->checked_in_at
                ->setTimezone($tenant->timezone ?? 'UTC')
                ->endOfDay()
                ->setTimezone('UTC');

            $attendance->update([
                'checked_out_at' => $endOfDay,
                'checked_out_by' => null,
                'edited_at'      => now(),
                'edited_by'      => null,
                'edit_note'      => 'Auto-checked out by system (stale check-in alert)',
            ]);

            try {
                $attendancePayments->captureAuthorized($attendance);
            } catch (\Throwable $e) {
                Log::warning('AlertStaleCheckins: Stripe capture skipped', [
                    'attendance_id' => $attendance->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    private function notifyStaff(Tenant $tenant, $records, NotificationService $notificationService): void
    {
        $checkoutUrl = URL::temporarySignedRoute(
            'admin.attendance.checkout-stale',
            now()->addDays(3),
            ['tenant' => $tenant->id],
        );

        $dogNames = $records->map(fn ($a) => $a->dog?->name)->filter()->values()->all();

        $payload = [
            'dog_count'   => count($dogNames),
            'dog_names'   => $dogNames,
            'checkout_url' => $checkoutUrl,
        ];

        if ($tenant->owner_user_id) {
            $notificationService->dispatch(
                'attendance.stale_checkins',
                $tenant->id,
                $tenant->owner_user_id,
                $payload,
            );
        }

        $staffUsers = User::allTenants()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'staff')
            ->where('status', 'active')
            ->get();

        foreach ($staffUsers as $staff) {
            $notificationService->dispatch(
                'attendance.stale_checkins',
                $tenant->id,
                $staff->id,
                $payload,
            );
        }
    }
}
