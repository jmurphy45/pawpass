<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKennelUnitRequest;
use App\Http\Requests\Admin\UpdateKennelUnitRequest;
use App\Http\Resources\KennelUnitResource;
use App\Models\KennelUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class KennelUnitController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $units = KennelUnit::orderBy('sort_order')->get();

        return KennelUnitResource::collection($units);
    }

    public function store(StoreKennelUnitRequest $request): KennelUnitResource
    {
        $unit = KennelUnit::create([
            'tenant_id'          => app('current.tenant.id'),
            'name'               => $request->name,
            'type'               => $request->type,
            'capacity'           => $request->capacity ?? 1,
            'description'        => $request->description,
            'is_active'          => $request->boolean('is_active', true),
            'sort_order'         => $request->sort_order ?? 0,
            'nightly_rate_cents' => $request->nightly_rate_cents,
        ]);

        return new KennelUnitResource($unit);
    }

    public function update(UpdateKennelUnitRequest $request, KennelUnit $kennelUnit): KennelUnitResource
    {
        $kennelUnit->update($request->only(['name', 'type', 'capacity', 'description', 'is_active', 'sort_order', 'nightly_rate_cents']));

        return new KennelUnitResource($kennelUnit->fresh());
    }

    public function destroy(KennelUnit $kennelUnit): JsonResource|JsonResponse
    {
        $hasActive = $kennelUnit->reservations()->where('status', '!=', 'cancelled')->exists();

        if ($hasActive) {
            return response()->json(['error' => 'UNIT_HAS_ACTIVE_RESERVATIONS'], 409);
        }

        $resource = new KennelUnitResource($kennelUnit);
        $kennelUnit->delete();

        return $resource;
    }
}
