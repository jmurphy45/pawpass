<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionPortalQrCode;
use App\Models\QrCode;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackfillPortalQrCodes extends Command
{
    protected $signature = 'qr-codes:backfill-portal';

    protected $description = 'Provision a portal QR code for any tenant that does not already have one';

    public function handle(): int
    {
        $tenantIds = Tenant::withTrashed()
            ->whereNotIn('id', QrCode::allTenants()->where('key', 'portal')->select('tenant_id'))
            ->pluck('id');

        if ($tenantIds->isEmpty()) {
            $this->info('All tenants already have a portal QR code.');

            return self::SUCCESS;
        }

        $this->info("Dispatching portal QR provisioning for {$tenantIds->count()} tenant(s)...");

        $tenantIds->each(fn ($id) => ProvisionPortalQrCode::dispatch($id));

        $this->info('Done.');

        return self::SUCCESS;
    }
}
