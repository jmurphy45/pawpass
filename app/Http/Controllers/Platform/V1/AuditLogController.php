<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        $entries = DB::table('platform_audit_log')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => $entries->items(),
            'meta' => [
                'total' => $entries->total(),
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }
}
