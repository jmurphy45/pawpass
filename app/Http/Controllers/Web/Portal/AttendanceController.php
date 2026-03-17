<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = Auth::user()->customer;
        $dogIds = $customer->dogs()->pluck('id');

        $query = Attendance::whereIn('dog_id', $dogIds)
            ->with('dog')
            ->orderByDesc('checked_in_at');

        if ($request->filled('dog_id')) {
            $dogId = $request->query('dog_id');
            if ($dogIds->contains($dogId)) {
                $query->where('dog_id', $dogId);
            }
        }

        $attendance = $query->paginate(20);

        $dogs = $customer->dogs()->orderBy('name')->get()->map(fn ($d) => [
            'id'   => $d->id,
            'name' => $d->name,
        ]);

        return Inertia::render('Portal/Attendance', [
            'attendance' => [
                'data' => collect($attendance->items())->map(fn ($a) => [
                    'id'             => $a->id,
                    'dog_name'       => $a->dog?->name ?? 'Unknown',
                    'checked_in_at'  => $a->checked_in_at->toIso8601String(),
                    'checked_out_at' => $a->checked_out_at?->toIso8601String(),
                    'credit_delta'   => -1,
                ]),
                'meta' => [
                    'total'        => $attendance->total(),
                    'per_page'     => $attendance->perPage(),
                    'current_page' => $attendance->currentPage(),
                    'last_page'    => $attendance->lastPage(),
                ],
            ],
            'dogs'          => $dogs,
            'selected_dog'  => $request->query('dog_id'),
        ]);
    }
}
