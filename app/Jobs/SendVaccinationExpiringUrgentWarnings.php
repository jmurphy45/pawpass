<?php

namespace App\Jobs;

use App\Models\DogVaccination;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendVaccinationExpiringUrgentWarnings implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService): void
    {
        $vaccinations = DogVaccination::allTenants()
            ->expiringUrgent()
            ->with(['dog.customer'])
            ->get();

        if ($vaccinations->isEmpty()) {
            return;
        }

        // Mark all as sent before dispatching — prevents duplicate floods if job crashes mid-loop
        DogVaccination::allTenants()
            ->expiringUrgent()
            ->update(['urgent_sent_at' => now()]);

        $byTenant = $vaccinations->groupBy('tenant_id');

        foreach ($byTenant as $tenantId => $tenantVaccinations) {
            try {
                $this->notifyTenantOwner($tenantId, $tenantVaccinations, $notificationService);
                $this->notifyCustomers($tenantId, $tenantVaccinations, $notificationService);
            } catch (\Throwable $e) {
                Log::error('SendVaccinationExpiringUrgentWarnings failed for tenant', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    private function notifyTenantOwner(string $tenantId, $tenantVaccinations, NotificationService $notificationService): void
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant || ! $tenant->owner_user_id) {
            return;
        }

        $notificationService->dispatch(
            'vaccinations.expiring_urgent',
            $tenantId,
            $tenant->owner_user_id,
            $this->buildPayload('expiring_urgent', $tenantVaccinations),
        );
    }

    private function notifyCustomers(string $tenantId, $tenantVaccinations, NotificationService $notificationService): void
    {
        $byUser = $tenantVaccinations->groupBy(fn ($v) => $v->dog?->customer?->user_id);

        foreach ($byUser as $userId => $customerVaccinations) {
            if (! $userId) {
                continue;
            }

            $notificationService->dispatch(
                'vaccinations.expiring_urgent',
                $tenantId,
                $userId,
                $this->buildPayload('expiring_urgent', $customerVaccinations),
            );
        }
    }

    private function buildPayload(string $level, $vaccinations): array
    {
        $dogs = $vaccinations
            ->groupBy('dog_id')
            ->map(function ($dogVaccinations) {
                $dog = $dogVaccinations->first()->dog;

                return [
                    'dog_id'       => $dog->id,
                    'dog_name'     => $dog->name,
                    'vaccinations' => $dogVaccinations->map(fn ($v) => [
                        'vaccination_id' => $v->id,
                        'vaccine_name'   => $v->vaccine_name,
                        'expires_at'     => $v->expires_at->toDateString(),
                        'days_remaining' => (int) now()->diffInDays($v->expires_at, false),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'level'             => $level,
            'dogs'              => $dogs,
            'dog_count'         => count($dogs),
            'vaccination_count' => $vaccinations->count(),
        ];
    }
}
