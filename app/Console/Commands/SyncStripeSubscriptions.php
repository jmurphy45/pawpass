<?php

namespace App\Console\Commands;

use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\StripeBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncStripeSubscriptions extends Command
{
    protected $signature = 'stripe:sync-subscriptions
                            {--dry-run : Print what would change without writing}
                            {--tenant= : Restrict to a single tenant ID}';

    protected $description = 'Sync platform billing subscription state from Stripe into the tenants table.';

    private const SYNCABLE_STATUSES = ['trialing', 'active', 'past_due'];

    private const STATUS_MAP = [
        'trialing' => 'trialing',
        'active'   => 'active',
        'past_due' => 'past_due',
    ];

    public function __construct(private readonly StripeBillingService $billing)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $query = Tenant::where(function ($q) {
            $q->whereNotNull('platform_stripe_customer_id')
              ->orWhereNotNull('platform_stripe_sub_id');
        });

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $synced = 0;
        $skipped = 0;

        $query->chunkById(100, function ($tenants) use ($dryRun, &$synced, &$skipped) {
            foreach ($tenants as $tenant) {
                if ($tenant->platform_stripe_customer_id) {
                    $subs = $this->billing->listSubscriptionsForCustomer($tenant->platform_stripe_customer_id);
                    $activeSub = collect($subs)->first(
                        fn ($s) => in_array($s->status, self::SYNCABLE_STATUSES, true)
                    );
                } elseif ($tenant->platform_stripe_sub_id) {
                    $sub = $this->billing->retrieveSubscription($tenant->platform_stripe_sub_id);
                    $activeSub = in_array($sub->status, self::SYNCABLE_STATUSES, true) ? $sub : null;
                } else {
                    $activeSub = null;
                }

                if (! $activeSub) {
                    $this->line("  SKIP {$tenant->slug} — no active/trialing subscription in Stripe");
                    $skipped++;
                    continue;
                }

                $newStatus = self::STATUS_MAP[$activeSub->status];
                $periodEndTimestamp = $activeSub->items->data[0]->current_period_end
                    ?? $activeSub->current_period_end
                    ?? $activeSub->trial_end
                    ?? null;
                $periodEnd = $periodEndTimestamp ? Carbon::createFromTimestamp($periodEndTimestamp) : null;
                $cancelAtEnd = (bool) $activeSub->cancel_at_period_end;

                $changes = [];

                if (! $tenant->platform_stripe_sub_id) {
                    $changes['platform_stripe_sub_id'] = $activeSub->id;
                }

                if ($tenant->status !== $newStatus) {
                    $changes['status'] = $newStatus;
                }

                if ($periodEnd && (! $tenant->plan_current_period_end || ! $tenant->plan_current_period_end->eq($periodEnd))) {
                    $changes['plan_current_period_end'] = $periodEnd;
                }

                if ($tenant->plan_cancel_at_period_end !== $cancelAtEnd) {
                    $changes['plan_cancel_at_period_end'] = $cancelAtEnd;
                }

                $this->line(sprintf(
                    '  %s %s (sub: %s, status: %s)',
                    $dryRun ? '[DRY]' : 'SYNC',
                    $tenant->slug,
                    $activeSub->id,
                    $activeSub->status,
                ));

                if ($changes) {
                    foreach ($changes as $col => $val) {
                        $display = $val instanceof Carbon ? $val->toDateTimeString() : var_export($val, true);
                        $this->line("        {$col} → {$display}");
                    }
                }

                if (! $dryRun) {
                    if ($changes) {
                        $tenant->update($changes);
                    }

                    $hasSubscribedEvent = PlatformSubscriptionEvent::where('tenant_id', $tenant->id)
                        ->where('event_type', 'subscribed')
                        ->exists();

                    if (! $hasSubscribedEvent) {
                        PlatformSubscriptionEvent::create([
                            'tenant_id'  => $tenant->id,
                            'event_type' => 'subscribed',
                            'payload'    => [
                                'stripe_sub_id' => $activeSub->id,
                                'status'        => $activeSub->status,
                                'source'        => 'backfill',
                            ],
                        ]);
                    }
                }

                $synced++;
            }
        });

        $this->info($dryRun
            ? "Dry run complete. Would sync: {$synced}, skip: {$skipped}."
            : "Done. Synced: {$synced}, skipped: {$skipped}."
        );

        return Command::SUCCESS;
    }
}
