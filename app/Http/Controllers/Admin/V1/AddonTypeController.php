<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAddonTypeRequest;
use App\Http\Requests\Admin\UpdateAddonTypeRequest;
use App\Http\Resources\AddonTypeResource;
use App\Models\AddonType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddonTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AddonTypeResource::collection(
            AddonType::orderBy('sort_order')->get()
        );
    }

    public function store(StoreAddonTypeRequest $request): JsonResponse
    {
        $addon = AddonType::create([
            'tenant_id'   => app('current.tenant.id'),
            'name'        => $request->name,
            'price_cents' => $request->price_cents,
            'is_active'   => $request->boolean('is_active', true),
            'sort_order'  => $request->sort_order ?? 0,
        ]);

        return response()->json(['data' => new AddonTypeResource($addon)], 201);
    }

    public function update(UpdateAddonTypeRequest $request, AddonType $addonType): JsonResponse
    {
        $addonType->update($request->only(['name', 'price_cents', 'is_active', 'sort_order']));

        return response()->json(['data' => new AddonTypeResource($addonType->fresh())]);
    }

    public function destroy(AddonType $addonType): JsonResponse
    {
        if ($addonType->reservationAddons()->exists()) {
            return response()->json(['error' => 'ADDON_TYPE_IN_USE'], 409);
        }

        $resource = new AddonTypeResource($addonType);
        $addonType->delete();

        return response()->json(['data' => $resource]);
    }
}
