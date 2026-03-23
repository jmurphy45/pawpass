<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\KennelUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupancyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to', now()->addDays(14)->toDateString());

        $request->merge(['from' => $from, 'to' => $to]);

        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        // Guard against excessively large ranges
        if (now()->parse($from)->diffInDays(now()->parse($to)) > 90) {
            return response()->json(['error' => 'DATE_RANGE_TOO_LARGE'], 422);
        }

        $units = KennelUnit::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['reservations' => function ($q) use ($from, $to) {
                $q->where('status', '!=', 'cancelled')
                    ->where('starts_at', '<', $to.' 23:59:59')
                    ->where('ends_at', '>', $from.' 00:00:00')
                    ->with(['dog:id,name', 'customer:id,name']);
            }])
            ->get();

        $data = $units->map(fn ($unit) => [
            'id'           => $unit->id,
            'name'         => $unit->name,
            'type'         => $unit->type,
            'reservations' => $unit->reservations->map(fn ($r) => [
                'id'            => $r->id,
                'dog_name'      => $r->dog?->name,
                'customer_name' => $r->customer?->name,
                'status'        => $r->status,
                'starts_at'     => $r->starts_at?->toIso8601String(),
                'ends_at'       => $r->ends_at?->toIso8601String(),
            ]),
        ]);

        return response()->json(['data' => $data, 'meta' => ['from' => $from, 'to' => $to]]);
    }
}
