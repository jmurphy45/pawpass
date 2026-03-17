<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function revenue(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to   = $request->input('to', now()->endOfDay()->toDateTimeString());

        $data = Cache::remember(
            'platform:revenue:snapshot',
            60 * 60 * 25,
            fn () => $this->reportService->platformRevenue($from, $to)
        );

        return response()->json(['data' => $data, 'meta' => []]);
    }

    public function tenantHealth(): JsonResponse
    {
        $data = Cache::remember(
            'platform:tenant_health:snapshot',
            60 * 60 * 25,
            fn () => $this->reportService->tenantHealth()
        );

        return response()->json(['data' => $data, 'meta' => []]);
    }

    public function notificationDelivery(Request $request): JsonResponse
    {
        $from     = $request->input('from', now()->subDays(30)->startOfDay()->toDateTimeString());
        $to       = $request->input('to', now()->endOfDay()->toDateTimeString());
        $tenantId = $request->input('tenant_id');

        $data = $this->reportService->notificationDelivery($from, $to, $tenantId);

        return response()->json(['data' => $data, 'meta' => []]);
    }
}
