<?php

namespace App\Http\Controllers\Public\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPlanResource;
use App\Models\PlatformPlan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlansController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $plans = PlatformPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return PlatformPlanResource::collection($plans);
    }
}
