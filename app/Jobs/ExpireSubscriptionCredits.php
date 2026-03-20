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
        $creditService       = app(DogCreditService::class);
        $notificationService = app(NotificationService::class);

        // Expire subscription credits (wipes entire balance)
        $subscriptionDogs = Dog::allTenants()
            ->with(['customer'])
            ->whereNotNull('credits_expire_at')
            ->where('credits_expire_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        foreach ($subscriptionDogs as $dog) {
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

        // Expire unlimited pass credits only (preserves non-expiring purchase credits)
        $passDogs = Dog::allTenants()
            ->with(['customer'])
            ->whereNotNull('unlimited_pass_expires_at')
            ->where('unlimited_pass_expires_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        foreach ($passDogs as $dog) {
            try {
                $creditService->expireUnlimitedPass($dog);

                if ($dog->auto_replenish_enabled) {
                    \App\Jobs\ProcessAutoReplenishJob::dispatch($dog->id);
                }
            } catch (\Throwable $e) {
                Log::error('ExpireSubscriptionCredits (unlimited pass) failed for dog', [
                    'dog_id' => $dog->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }
}
