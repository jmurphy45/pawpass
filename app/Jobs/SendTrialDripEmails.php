<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\TenantEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTrialDripEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(NotificationService $notifications, TenantEventService $events): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        Tenant::where('status', 'trialing')
            ->whereNotNull('owner_user_id')
            ->each(function (Tenant $tenant) use ($host, $notifications, $events): void {
                $days = (int) $tenant->trial_started_at->diffInDays(now());
                $base = 'https://'.$tenant->slug.'.'.$host.'/admin';

                // Day 1+: Stripe not connected
                if ($days >= 1 && ! $tenant->stripe_account_id) {
                    if ($events->recordOnce($tenant->id, 'email_drip.stripe_nudge')) {
                        $notifications->dispatch('onboarding.stripe_nudge', $tenant->id, $tenant->owner_user_id, [
                            'billing_url' => $base.'/billing',
                        ]);
                    }
                }

                // Day 3+: No customers added yet
                if ($days >= 3 && Customer::where('tenant_id', $tenant->id)->doesntExist()) {
                    if ($events->recordOnce($tenant->id, 'email_drip.first_customer_nudge')) {
                        $notifications->dispatch('onboarding.first_customer_nudge', $tenant->id, $tenant->owner_user_id, [
                            'customer_url' => $base.'/customers/create',
                        ]);
                    }
                }

                // Day 7+: No check-ins yet
                if ($days >= 7 && Attendance::where('tenant_id', $tenant->id)->doesntExist()) {
                    if ($events->recordOnce($tenant->id, 'email_drip.first_checkin_nudge')) {
                        $notifications->dispatch('onboarding.first_checkin_nudge', $tenant->id, $tenant->owner_user_id, [
                            'roster_url' => $base.'/roster',
                        ]);
                    }
                }

                // Day 10+: Halfway upgrade nudge (always, regardless of activity)
                if ($days >= 10) {
                    if ($events->recordOnce($tenant->id, 'email_drip.halfway_upgrade')) {
                        $notifications->dispatch('onboarding.halfway_upgrade', $tenant->id, $tenant->owner_user_id, [
                            'billing_url' => $base.'/billing',
                        ]);
                    }
                }
            });
    }
}
