<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index');
    }

    public function revenue(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from     = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to       = $request->input('to', now()->toDateString());
        $groupBy  = $request->input('group_by', 'month');

        $data = $this->reportService->revenue(
            $tenantId,
            $from.' 00:00:00',
            $to.' 23:59:59',
            $groupBy
        );

        return Inertia::render('Admin/Reports/Revenue', [
            'rows'    => $data,
            'filters' => compact('from', 'to', 'groupBy'),
        ]);
    }

    public function packages(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from     = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to       = $request->input('to', now()->toDateString());

        $data = $this->reportService->packages($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Packages', [
            'rows'    => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function credits(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from     = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to       = $request->input('to', now()->toDateString());

        $data = $this->reportService->credits($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Credits', [
            'rows'    => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function customers(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from     = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to       = $request->input('to', now()->toDateString());

        $data = $this->reportService->customersLtv($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Customers', [
            'rows'    => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function attendance(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from     = $request->input('from', now()->subDays(30)->toDateString());
        $to       = $request->input('to', now()->toDateString());
        $groupBy  = $request->input('group_by', 'day');

        $data = $this->reportService->attendance(
            $tenantId,
            $from.' 00:00:00',
            $to.' 23:59:59',
            $groupBy
        );

        return Inertia::render('Admin/Reports/Attendance', [
            'rows'    => $data,
            'filters' => compact('from', 'to', 'groupBy'),
        ]);
    }

    public function creditStatus(): Response
    {
        $tenantId = app('current.tenant.id');
        $data     = $this->reportService->creditStatus($tenantId);

        return Inertia::render('Admin/Reports/CreditStatus', ['data' => $data]);
    }
}
