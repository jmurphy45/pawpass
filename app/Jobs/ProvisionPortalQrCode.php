<?php

namespace App\Jobs;

use App\Models\QrCode;
use App\Models\Tenant;
use App\Services\QrCodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionPortalQrCode implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tenantId) {}

    public function handle(QrCodeService $qrCodes): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            return;
        }

        $exists = QrCode::where('tenant_id', $tenant->id)->where('key', 'portal')->exists();

        if ($exists) {
            return;
        }

        QrCode::create([
            'tenant_id' => $tenant->id,
            'token' => $qrCodes->generateToken(),
            'key' => 'portal',
            'target_url' => '/my',
            'label' => 'Customer Portal',
        ]);
    }
}
