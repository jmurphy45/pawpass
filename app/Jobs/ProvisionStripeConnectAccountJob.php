<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionStripeConnectAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private readonly Tenant $tenant)
    {
        $this->onQueue('stripe');
    }

    public function handle(StripeService $stripe): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant->stripe_account_id) {
            return;
        }

        $owner = $tenant->owner;

        if (! $owner) {
            Log::error('stripe_connect.provision_failed: owner not found', ['tenant_id' => $tenant->id]);

            return;
        }

        try {
            $account = $stripe->createConnectAccount($owner->email, $tenant->name);
            $tenant->update(['stripe_account_id' => $account->id]);
            Log::info('stripe_connect.provisioned', ['tenant_id' => $tenant->id, 'account_id' => $account->id]);
        } catch (Throwable $e) {
            Log::error('stripe_connect.provision_failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
