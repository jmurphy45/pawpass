<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dog;
use App\Services\ReportService;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly VaccinationComplianceService $complianceService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index');
    }

    public function revenue(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $groupBy = $request->input('group_by', 'month');

        $data = $this->reportService->revenue(
            $tenantId,
            $from.' 00:00:00',
            $to.' 23:59:59',
            $groupBy
        );

        return Inertia::render('Admin/Reports/Revenue', [
            'rows' => $data,
            'filters' => compact('from', 'to', 'groupBy'),
        ]);
    }

    public function packages(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->reportService->packages($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Packages', [
            'rows' => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function credits(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->reportService->credits($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Credits', [
            'rows' => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function customers(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->reportService->customersLtv($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Customers', [
            'rows' => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function attendance(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());
        $groupBy = $request->input('group_by', 'day');

        $data = $this->reportService->attendance(
            $tenantId,
            $from.' 00:00:00',
            $to.' 23:59:59',
            $groupBy
        );

        return Inertia::render('Admin/Reports/Attendance', [
            'rows' => $data,
            'filters' => compact('from', 'to', 'groupBy'),
        ]);
    }

    public function creditStatus(): Response
    {
        $tenantId = app('current.tenant.id');
        $data = $this->reportService->creditStatus($tenantId);

        return Inertia::render('Admin/Reports/CreditStatus', ['data' => $data]);
    }

    public function promotions(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->reportService->promotions($tenantId, $from.' 00:00:00', $to.' 23:59:59');

        return Inertia::render('Admin/Reports/Promotions', [
            'rows' => $data,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function boardingRevenue(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $from = $request->input('from', now()->subMonths(12)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $groupBy = $request->input('group_by', 'month');

        $data = $this->reportService->boardingRevenue(
            $tenantId,
            $from.' 00:00:00',
            $to.' 23:59:59',
            $groupBy
        );

        return Inertia::render('Admin/Reports/Boarding', [
            'rows' => $data,
            'filters' => compact('from', 'to', 'groupBy'),
        ]);
    }

    public function outstandingBalances(): Response
    {
        $tenantId = app('current.tenant.id');
        $data = $this->reportService->outstandingBalances($tenantId);

        return Inertia::render('Admin/Reports/OutstandingBalances', ['rows' => $data]);
    }

    public function vaccinations(Request $request): Response
    {
        $tenantId = app('current.tenant.id');
        $filter = $request->input('filter'); // null|'non_compliant'|'expiring_soon'|'expiring_urgent'

        $dogs = Dog::with(['vaccinations', 'customer'])
            ->whereHas('customer')
            ->get();

        $rows = $dogs->map(function (Dog $dog) use ($tenantId) {
            $vaccinationStatus = $this->complianceService->getVaccinationStatus($dog, $tenantId);
            $isCompliant = $this->complianceService->isCompliant($dog, $tenantId);

            return [
                'dog_id' => $dog->id,
                'dog_name' => $dog->name,
                'customer_id' => $dog->customer->id,
                'customer_name' => $dog->customer->name,
                'is_compliant' => $isCompliant,
                'vaccinations' => $vaccinationStatus,
            ];
        });

        if ($filter === 'non_compliant') {
            $rows = $rows->filter(fn ($r) => ! $r['is_compliant'])->values();
        } elseif ($filter === 'expiring_soon') {
            $rows = $rows->filter(function ($r) {
                foreach ($r['vaccinations'] as $v) {
                    if ($v['days_remaining'] !== null && $v['days_remaining'] >= 0 && $v['days_remaining'] <= 30) {
                        return true;
                    }
                }

                return false;
            })->values();
        } elseif ($filter === 'expiring_urgent') {
            $rows = $rows->filter(function ($r) {
                foreach ($r['vaccinations'] as $v) {
                    if ($v['days_remaining'] !== null && $v['days_remaining'] >= 0 && $v['days_remaining'] <= 7) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        return Inertia::render('Admin/Reports/Vaccinations', [
            'rows' => $rows->values(),
            'filter' => $filter,
        ]);
    }
}
