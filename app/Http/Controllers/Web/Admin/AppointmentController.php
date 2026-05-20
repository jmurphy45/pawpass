<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function calendar(Request $request): Response
    {
        $weekStart = $request->filled('week')
            ? \Carbon\Carbon::parse($request->week)->startOfWeek(\Carbon\Carbon::SUNDAY)
            : now()->startOfWeek(\Carbon\Carbon::SUNDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

        $appointments = Appointment::with(['dog:id,name', 'bookableResource:id,name,resource_type'])
            ->whereBetween('starts_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Admin/Appointments/Calendar', [
            'appointments' => $appointments,
            'weekStart' => $weekStart->toDateString(),
        ]);
    }

    public function index(Request $request): Response
    {
        $query = Appointment::with(['dog:id,name', 'customer:id,name', 'bookableResource:id,name,resource_type'])
            ->orderBy('starts_at', 'desc');

        if ($request->filled('service_type')) {
            $query->ofType($request->service_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        $appointments = $query->cursorPaginate(25)->withQueryString();

        return Inertia::render('Admin/Appointments/Index', [
            'appointments' => $appointments,
            'filters' => $request->only(['service_type', 'status', 'date']),
        ]);
    }
}
