<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function revenue(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());
        $groupBy = $request->input('group_by', 'month');

        $data = Cache::remember(
            "report:{$tenantId}:revenue",
            60 * 60 * 25,
            fn () => $this->reportService->revenue($tenantId, $from, $to, $groupBy)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['period', 'gross', 'fee', 'net', 'orders'], 'revenue');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function payoutForecast(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');

        $data = Cache::remember(
            "report:{$tenantId}:payout_forecast",
            60 * 5,
            fn () => $this->reportService->payoutForecast($tenantId)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse([$data], ['period', 'gross', 'fee', 'net', 'orders'], 'payout_forecast');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function packages(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());

        $data = Cache::remember(
            "report:{$tenantId}:packages",
            60 * 60 * 25,
            fn () => $this->reportService->packages($tenantId, $from, $to)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['package_id', 'package_name', 'package_type', 'orders', 'revenue'], 'packages');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function credits(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());

        $data = Cache::remember(
            "report:{$tenantId}:credits",
            60 * 60 * 25,
            fn () => $this->reportService->credits($tenantId, $from, $to)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['type', 'total_delta', 'entries'], 'credits');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function customersLtv(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());

        $data = Cache::remember(
            "report:{$tenantId}:customers_ltv",
            60 * 60 * 25,
            fn () => $this->reportService->customersLtv($tenantId, $from, $to)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['customer_id', 'customer_name', 'orders', 'total_spend'], 'customers_ltv');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function attendance(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subDays(30)->startOfDay()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());
        $groupBy = $request->input('group_by', 'day');

        $data = $this->reportService->attendance($tenantId, $from, $to, $groupBy);

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['period', 'checkins', 'unique_dogs'], 'attendance');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function rosterHistory(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $date = $request->input('date', now()->toDateString());

        $data = $this->reportService->rosterHistory($tenantId, $date);

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['id', 'dog_name', 'customer_name', 'checked_in_at', 'checked_out_at'], 'roster_history');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function creditStatus(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');

        $data = $this->reportService->creditStatus($tenantId);

        if ($request->input('format') === 'csv') {
            $flat = array_merge(
                array_map(fn ($d) => array_merge($d, ['category' => 'zero']), $data['zero']),
                array_map(fn ($d) => array_merge($d, ['category' => 'low']), $data['low'])
            );

            return $this->csvResponse($flat, ['id', 'dog_name', 'customer_name', 'credit_balance', 'category'], 'credit_status');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function staffActivity(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subDays(30)->startOfDay()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());
        $userId = $request->input('user_id');

        $data = $this->reportService->staffActivity($tenantId, $from, $to, $userId);

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['user_id', 'user_name', 'checkins'], 'staff_activity');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function promotions(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());

        $data = Cache::remember(
            "report:{$tenantId}:promotions",
            60 * 60 * 25,
            fn () => $this->reportService->promotions($tenantId, $from, $to)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['code', 'name', 'type', 'discount_value', 'redemptions', 'total_discount_cents'], 'promotions');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function boardingRevenue(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateTimeString());
        $to = $request->input('to', now()->endOfDay()->toDateTimeString());
        $groupBy = $request->input('group_by', 'month');

        $data = Cache::remember(
            "report:{$tenantId}:boarding_revenue",
            60 * 60 * 25,
            fn () => $this->reportService->boardingRevenue($tenantId, $from, $to, $groupBy)
        );

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['period', 'gross', 'fee', 'net', 'orders'], 'boarding_revenue');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function outstandingBalances(Request $request): JsonResponse|StreamedResponse
    {
        $tenantId = app('current.tenant.id');

        $data = $this->reportService->outstandingBalances($tenantId);

        if ($request->input('format') === 'csv') {
            return $this->csvResponse($data, ['customer_name', 'email', 'outstanding_balance_cents', 'charge_pending_at'], 'outstanding_balances');
        }

        return response()->json(['data' => $data, 'meta' => ['tenant_id' => $tenantId]]);
    }

    private function csvResponse(array $rows, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                $rowArray = is_array($row) ? $row : (array) $row;
                fputcsv($out, array_map(fn ($col) => $rowArray[$col] ?? '', $columns));
            }
            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }
}
