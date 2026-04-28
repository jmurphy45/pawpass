<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerIntelligenceService;
use Inertia\Inertia;
use Inertia\Response;

class CustomerIntelligenceController extends Controller
{
    public function __construct(
        private readonly CustomerIntelligenceService $service
    ) {}

    public function __invoke(): Response
    {
        $tenantId = app('current.tenant.id');

        return Inertia::render('Admin/Reports/CustomerIntelligence', [
            'churnRisk' => $this->service->churnRisk($tenantId),
            'priceSensitivity' => $this->service->priceSensitivity($tenantId),
            'packageFit' => $this->service->packageFit($tenantId),
        ]);
    }
}
