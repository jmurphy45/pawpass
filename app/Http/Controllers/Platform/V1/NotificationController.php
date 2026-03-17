<?php

namespace App\Http\Controllers\Platform\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function delivery(): JsonResponse
    {
        $stats = DB::table('notification_logs')
            ->selectRaw('channel, status, count(*) as count')
            ->groupBy('channel', 'status')
            ->get();

        return response()->json(['data' => $stats]);
    }

    public function retry(string $logId, NotificationService $notifications): JsonResponse
    {
        $log = DB::table('notification_logs')->where('id', $logId)->first();

        if (! $log) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($log->status !== 'failed') {
            return response()->json(['message' => 'Only failed notifications can be retried.'], 422);
        }

        $context = [];
        if ($log->notification_id) {
            $notification = DB::table('notifications')->where('id', $log->notification_id)->first();
            if ($notification) {
                $data = json_decode($notification->data, true);
                $context = $data['data'] ?? [];
            }
        }

        $notifications->dispatch($log->type, $log->tenant_id, $log->user_id, $context);

        return response()->json(['data' => ['message' => 'Retry dispatched.']]);
    }
}
