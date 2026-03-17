<?php

namespace App\Http\Controllers\Web\Admin;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Tenant;
use App\Services\DogCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function __construct(private DogCreditService $credits) {}

    public function index(): Response
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $threshold = $tenant?->low_credit_threshold ?? 2;

        $dogs = Dog::with(['attendances' => function ($q) {
            $q->whereDate('checked_in_at', today())->orderByDesc('checked_in_at');
        }, 'customer'])->get();

        $roster = $dogs->map(function (Dog $dog) use ($threshold) {
            $todayAttendance = $dog->attendances->first();

            $attendanceState = match (true) {
                ! $todayAttendance => 'not_in',
                $todayAttendance->checked_out_at === null => 'checked_in',
                default => 'done',
            };

            $creditStatus = match (true) {
                $dog->credit_balance <= 0 => 'empty',
                $dog->credit_balance <= $threshold => 'low',
                default => 'ready',
            };

            return [
                'id'              => $dog->id,
                'name'            => $dog->name,
                'customer_name'   => $dog->customer?->name,
                'credit_balance'  => $dog->credit_balance,
                'credit_status'   => $creditStatus,
                'attendance_state' => $attendanceState,
            ];
        });

        return Inertia::render('Admin/Roster/Index', [
            'roster' => $roster,
        ]);
    }

    public function checkin(Request $request): RedirectResponse
    {
        $request->validate([
            'dog_id'               => ['required', 'string'],
            'zero_credit_override' => ['boolean'],
            'override_note'        => ['nullable', 'string'],
        ]);

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $staffUser = auth()->user();

        $dog = Dog::find($request->dog_id);

        if (! $dog) {
            return back()->with('error', 'Dog not found.');
        }

        $openAttendance = Attendance::where('dog_id', $dog->id)
            ->whereDate('checked_in_at', today())
            ->whereNull('checked_out_at')
            ->exists();

        if ($openAttendance) {
            return back()->with('error', 'Dog is already checked in today.');
        }

        $override = $request->boolean('zero_credit_override');
        $hasUnlimitedPass = $dog->unlimited_pass_expires_at?->isFuture();

        if ($dog->credit_balance <= 0 && ! $hasUnlimitedPass && $tenant->checkin_block_at_zero && ! $override) {
            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        $attendance = Attendance::create([
            'tenant_id'            => $tenantId,
            'dog_id'               => $dog->id,
            'checked_in_by'        => $staffUser->id,
            'checked_in_at'        => now(),
            'zero_credit_override' => $override,
            'override_note'        => $request->override_note,
        ]);

        try {
            $this->credits->deductForAttendance($attendance);
        } catch (InsufficientCreditsException $e) {
            $attendance->delete();

            return back()->with('error', 'Cannot check in dog with zero credits.');
        }

        return back()->with('success', "{$dog->name} checked in.");
    }

    public function checkout(Request $request): RedirectResponse
    {
        $request->validate([
            'dog_id' => ['required', 'string'],
        ]);

        $attendance = Attendance::where('dog_id', $request->dog_id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $attendance) {
            return back()->with('error', 'No open attendance found for this dog.');
        }

        $attendance->update([
            'checked_out_at' => now(),
            'checked_out_by' => auth()->id(),
        ]);

        $dog = Dog::find($request->dog_id);

        return back()->with('success', "{$dog?->name} checked out.");
    }
}
