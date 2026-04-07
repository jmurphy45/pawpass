<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Services\KennelAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KennelUnitController extends Controller
{
    public function __construct(
        private readonly KennelAvailabilityService $availability,
    ) {}

    public function checkAvailability(Request $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
        ]);

        $units = $this->availability->availableUnits(
            $tenantId,
            now()->parse($request->starts_at),
            now()->parse($request->ends_at),
        );

        return response()->json([
            'data' => $units->map(fn ($u) => [
                'id'                 => $u->id,
                'name'               => $u->name,
                'type'               => $u->type,
                'description'        => $u->description,
                'nightly_rate_cents' => $u->nightly_rate_cents,
            ])->values(),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $tenant = auth()->user()->tenant ?? \App\Models\Tenant::find($tenantId);

        if ($tenant && $tenant->isDaycare()) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
        ]);

        $startsAt = now()->parse($request->starts_at);
        $endsAt = now()->parse($request->ends_at);

        $units = $this->availability->availableUnits($tenantId, $startsAt, $endsAt);

        return response()->json([
            'data' => $units->map(fn ($u) => [
                'id'                 => $u->id,
                'name'               => $u->name,
                'type'               => $u->type,
                'description'        => $u->description,
                'nightly_rate_cents' => $u->nightly_rate_cents,
            ])->values(),
        ]);
    }
}
