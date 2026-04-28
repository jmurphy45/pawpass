<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerIntelligenceService;
use Illuminate\Http\JsonResponse;

class CustomerIntelligenceController extends Controller
{
    public function __construct(
        private readonly CustomerIntelligenceService $service
    ) {}

    public function __invoke(): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        return response()->json([
            'data' => [
                'churn_risk' => $this->service->churnRisk($tenantId),
                'price_sensitivity' => $this->service->priceSensitivity($tenantId),
                'package_fit' => $this->service->packageFit($tenantId),
            ],
            'meta' => ['tenant_id' => $tenantId],
        ]);
    }
}
