<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPlanResource;
use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;
use App\Services\PlanFeatureCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformPlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $plans = PlatformPlan::orderBy('sort_order')->get();

        return PlatformPlanResource::collection($plans);
    }

    public function store(Request $request): PlatformPlanResource
    {
        $data = $request->validate([
            'slug'                => 'required|string|unique:platform_plans,slug',
            'name'                => 'required|string',
            'description'         => 'nullable|string',
            'monthly_price_cents' => 'required|integer|min:0',
            'annual_price_cents'  => 'nullable|integer|min:0',
            'features'            => 'required|array',
            'features.*'          => 'string',
            'staff_limit'                => 'nullable|integer|min:1',
            'sms_segment_quota'          => 'nullable|integer|min:0',
            'sms_cost_per_segment_cents' => 'nullable|integer|min:0',
            'sort_order'                 => 'nullable|integer',
        ]);

        $plan = PlatformPlan::create(array_merge($data, ['is_active' => true]));

        return new PlatformPlanResource($plan->fresh());
    }

    public function update(Request $request, string $id): PlatformPlanResource
    {
        $plan = PlatformPlan::findOrFail($id);

        $data = $request->validate([
            'features'    => 'sometimes|array',
            'features.*'                 => 'string',
            'staff_limit'                => 'sometimes|integer|min:1',
            'sms_segment_quota'          => 'sometimes|integer|min:0',
            'sms_cost_per_segment_cents' => 'sometimes|integer|min:0',
            'name'                       => 'sometimes|string',
            'description'                => 'nullable|string',
            'is_active'                  => 'sometimes|boolean',
        ]);

        $plan->update($data);

        app(PlanFeatureCache::class); // singleton — reset handled by container lifecycle

        return new PlatformPlanResource($plan->fresh());
    }

    public function syncStripe(Request $request, string $id): PlatformPlanResource
    {
        $plan = PlatformPlan::findOrFail($id);

        SyncPlatformPlanToStripe::dispatchSync($plan);

        return new PlatformPlanResource($plan->fresh());
    }
}
