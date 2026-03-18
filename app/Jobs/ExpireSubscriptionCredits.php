<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionCredits implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $dogs = Dog::allTenants()
            ->with(['customer'])
            ->whereNotNull('credits_expire_at')
            ->where('credits_expire_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        $creditService       = app(DogCreditService::class);
        $notificationService = app(NotificationService::class);

        foreach ($dogs as $dog) {
            try {
                $creditService->expireCredits($dog);

                $dog->update(['credits_expire_at' => null, 'credits_alert_sent_at' => null]);

                if (! $dog->customer) {
                    continue;
                }

                $userId = $dog->customer->user_id;

                if (! $userId) {
                    continue;
                }

                $notificationService->dispatch(
                    'credits.empty',
                    $dog->tenant_id,
                    $userId,
                    ['dog_id' => $dog->id],
                );
            } catch (\Throwable $e) {
                Log::error('ExpireSubscriptionCredits failed for dog', [
                    'dog_id' => $dog->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }
}
