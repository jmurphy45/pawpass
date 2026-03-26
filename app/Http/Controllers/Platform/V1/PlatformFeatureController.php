<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformFeatureResource;
use App\Models\PlatformFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformFeatureController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $features = PlatformFeature::orderBy('sort_order')->get();

        return PlatformFeatureResource::collection($features);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug'         => 'required|string|unique:platform_features,slug',
            'name'         => 'required|string',
            'description'  => 'nullable|string',
            'is_marketing' => 'boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        $feature = PlatformFeature::create($data);

        return (new PlatformFeatureResource($feature))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): PlatformFeatureResource
    {
        $feature = PlatformFeature::findOrFail($id);

        $data = $request->validate([
            'name'         => 'sometimes|string',
            'description'  => 'nullable|string',
            'is_marketing' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer',
        ]);

        $feature->update($data);

        return new PlatformFeatureResource($feature->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        PlatformFeature::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
