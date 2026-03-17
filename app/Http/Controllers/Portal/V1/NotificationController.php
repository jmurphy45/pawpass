<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = Notification::where('notifiable_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return NotificationResource::collection($notifications);
    }

    public function count(Request $request): JsonResponse
    {
        $count = Notification::where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['unread' => $count]]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            abort(404);
        }

        $notification = Notification::where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => ['message' => 'Notification marked as read.']]);
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['message' => 'All notifications marked as read.']]);
    }
}
