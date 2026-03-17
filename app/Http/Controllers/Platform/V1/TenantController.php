<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withTrashed()->with('owner');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tenants = $query->get()->map(fn ($t) => $this->tenantData($t));

        return response()->json(['data' => $tenants]);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::withTrashed()->with('owner')->find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => $this->tenantData($tenant, detailed: true)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'platform_fee_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'payout_schedule' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
        ]);

        $tenant->update($validated);

        return response()->json(['data' => $this->tenantData($tenant->fresh())]);
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        return $this->transition($id, 'suspended', 'tenant.suspended', $request->reason);
    }

    public function reinstate(Request $request, string $id): JsonResponse
    {
        return $this->transition($id, 'active', 'tenant.reinstated', $request->reason);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->transition($id, 'cancelled', 'tenant.cancelled', $request->reason);
    }

    private function transition(string $id, string $status, string $action, ?string $reason): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($tenant->status === $status) {
            return response()->json(['message' => 'Tenant is already in this state.'], 422);
        }

        $tenant->update(['status' => $status]);

        DB::table('platform_audit_log')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => auth()->id(),
            'actor_role' => 'platform_admin',
            'action' => $action,
            'target_type' => 'tenant',
            'target_id' => $id,
            'context' => json_encode(['reason' => $reason]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return response()->json(['data' => $this->tenantData($tenant->fresh())]);
    }

    private function tenantData(Tenant $tenant, bool $detailed = false): array
    {
        $data = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'platform_fee_pct' => $tenant->platform_fee_pct,
            'payout_schedule' => $tenant->payout_schedule,
        ];

        if ($detailed) {
            $data['owner'] = $tenant->owner
                ? ['id' => $tenant->owner->id, 'name' => $tenant->owner->name]
                : null;
            $data['user_count'] = $tenant->users()->count();
        }

        return $data;
    }
}
