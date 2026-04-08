<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TenantEvent::query()->orderByDesc('created_at');

        if ($tenantId = $request->query('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        if ($eventType = $request->query('event_type')) {
            $query->where('event_type', $eventType);
        }

        $events = $query->paginate(15);

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'total' => $events->total(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }
}
