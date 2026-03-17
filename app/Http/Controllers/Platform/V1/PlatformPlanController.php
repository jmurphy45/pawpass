<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPlanResource;
use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;
use App\Services\PlanFeatureCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = PlatformPlan::orderBy('sort_order')->get();

        return response()->json(['data' => PlatformPlanResource::collection($plans)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug'                => 'required|string|unique:platform_plans,slug',
            'name'                => 'required|string',
            'description'         => 'nullable|string',
            'monthly_price_cents' => 'required|integer|min:0',
            'annual_price_cents'  => 'nullable|integer|min:0',
            'features'            => 'required|array',
            'features.*'          => 'string',
            'staff_limit'         => 'nullable|integer|min:1',
            'sort_order'          => 'nullable|integer',
        ]);

        $plan = PlatformPlan::create(array_merge($data, ['is_active' => true]));

        return response()->json(['data' => new PlatformPlanResource($plan->fresh())], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = PlatformPlan::findOrFail($id);

        $data = $request->validate([
            'features'    => 'sometimes|array',
            'features.*'  => 'string',
            'staff_limit' => 'sometimes|integer|min:1',
            'name'        => 'sometimes|string',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $plan->update($data);

        app(PlanFeatureCache::class); // singleton — reset handled by container lifecycle

        return response()->json(['data' => new PlatformPlanResource($plan->fresh())]);
    }

    public function syncStripe(Request $request, string $id): JsonResponse
    {
        $plan = PlatformPlan::findOrFail($id);

        SyncPlatformPlanToStripe::dispatchSync($plan);

        return response()->json(['data' => new PlatformPlanResource($plan->fresh())]);
    }
}
