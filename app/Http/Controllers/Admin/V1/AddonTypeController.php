<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAddonTypeRequest;
use App\Http\Requests\Admin\UpdateAddonTypeRequest;
use App\Http\Resources\AddonTypeResource;
use App\Models\AddonType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddonTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AddonType::orderBy('sort_order');

        if ($context = $request->input('context')) {
            $query->where(function ($q) use ($context) {
                $q->where('context', $context)->orWhere('context', 'both');
            });
        }

        return AddonTypeResource::collection($query->get());
    }

    public function store(StoreAddonTypeRequest $request): JsonResponse
    {
        $addon = AddonType::create([
            'tenant_id'   => app('current.tenant.id'),
            'name'        => $request->name,
            'price_cents' => $request->price_cents,
            'is_active'   => $request->boolean('is_active', true),
            'sort_order'  => $request->sort_order ?? 0,
            'context'     => $request->input('context', 'both'),
        ]);

        return response()->json(['data' => new AddonTypeResource($addon)], 201);
    }

    public function update(UpdateAddonTypeRequest $request, AddonType $addonType): JsonResponse
    {
        $addonType->update($request->only(['name', 'price_cents', 'is_active', 'sort_order', 'context']));

        return response()->json(['data' => new AddonTypeResource($addonType->fresh())]);
    }

    public function destroy(AddonType $addonType): JsonResponse
    {
        if ($addonType->reservationAddons()->exists() || $addonType->attendanceAddons()->exists()) {
            return response()->json(['error' => 'ADDON_TYPE_IN_USE'], 409);
        }

        $resource = new AddonTypeResource($addonType);
        $addonType->delete();

        return response()->json(['data' => $resource]);
    }
}
