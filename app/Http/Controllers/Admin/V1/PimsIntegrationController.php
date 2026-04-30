<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\PimsIntegration;
use App\Models\PimsSyncLog;
use App\Services\Pims\PimsAdapterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PimsIntegrationController extends Controller
{
    public function __construct(private readonly PimsAdapterRegistry $registry) {}

    public function providers(): JsonResponse
    {
        return response()->json(['data' => $this->registry->providers()]);
    }

    public function index(): JsonResponse
    {
        $integrations = PimsIntegration::orderBy('created_at')->get()->map(fn ($i) => $this->format($i));

        return response()->json(['data' => $integrations]);
    }

    public function store(Request $request): JsonResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string'],
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'credentials' => ['required', 'array'],
        ]);

        // Ensure the provider is registered.
        $this->registry->for($validated['provider']);

        $integration = PimsIntegration::create([
            'tenant_id' => app('current.tenant.id'),
            'provider' => $validated['provider'],
            'api_base_url' => $validated['api_base_url'] ?? null,
            'credentials' => $validated['credentials'],
            'status' => 'active',
        ]);

        return response()->json(['data' => $this->format($integration)], 201);
    }

    public function update(Request $request, PimsIntegration $pimsIntegration): JsonResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'credentials' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', 'in:active,disabled'],
        ]);

        if (isset($validated['credentials'])) {
            // Merge new credentials over existing so callers can patch a single key.
            $validated['credentials'] = array_merge(
                $pimsIntegration->credentials ?? [],
                $validated['credentials'],
            );
        }

        $pimsIntegration->update($validated);

        return response()->json(['data' => $this->format($pimsIntegration->fresh())]);
    }

    public function destroy(PimsIntegration $pimsIntegration): JsonResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $pimsIntegration->delete();

        return response()->json(null, 204);
    }

    public function testConnection(PimsIntegration $pimsIntegration): JsonResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        try {
            $adapter = $this->registry->for($pimsIntegration->provider);
            $adapter->testConnection($pimsIntegration);

            return response()->json(['data' => ['success' => true]]);
        } catch (\Throwable $e) {
            $pimsIntegration->update(['status' => 'error', 'sync_error' => $e->getMessage()]);

            return response()->json(['data' => ['success' => false, 'error' => $e->getMessage()]], 422);
        }
    }

    public function syncLogs(Request $request, PimsIntegration $pimsIntegration): JsonResponse
    {
        $logs = PimsSyncLog::where('tenant_id', $pimsIntegration->tenant_id)
            ->where('provider', $pimsIntegration->provider)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    private function format(PimsIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider,
            'provider_label' => $this->registry->for($integration->provider)->providerLabel(),
            'api_base_url' => $integration->api_base_url,
            'status' => $integration->status,
            'last_full_sync_at' => $integration->last_full_sync_at?->toIso8601String(),
            'last_delta_sync_at' => $integration->last_delta_sync_at?->toIso8601String(),
            'sync_error' => $integration->sync_error,
            'created_at' => $integration->created_at->toIso8601String(),
        ];
    }
}
