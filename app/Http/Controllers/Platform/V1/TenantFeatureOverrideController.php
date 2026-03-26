<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeature;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class TenantFeatureOverrideController extends Controller
{
    public function index(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $slugs  = PlatformFeature::pluck('slug')->all();
        $scope  = get_class($tenant).'|'.$tenant->id;

        // Try DB store first (database driver); fall back to checking all features via Pennant
        $dbRows = DB::table('features')->where('scope', $scope)->pluck('value', 'name');

        $overrides = collect($slugs)
            ->filter(fn ($slug) => isset($dbRows[$slug]))
            ->map(fn ($slug) => [
                'feature_slug' => $slug,
                'value'        => json_decode($dbRows[$slug], true),
            ])
            ->values();

        return response()->json(['data' => $overrides]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'feature_slug' => ['required', 'string', 'exists:platform_features,slug'],
        ]);

        Feature::for($tenant)->activate($data['feature_slug']);

        return response()->json(['data' => ['feature_slug' => $data['feature_slug'], 'value' => true]], 201);
    }

    public function destroy(string $tenantId, string $featureSlug): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        PlatformFeature::where('slug', $featureSlug)->firstOrFail();

        Feature::for($tenant)->forget($featureSlug);

        return response()->json(null, 204);
    }
}
