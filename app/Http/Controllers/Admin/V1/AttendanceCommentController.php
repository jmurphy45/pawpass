<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceCommentResource;
use App\Models\Attendance;
use App\Models\AttendanceComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCommentController extends Controller
{
    public function index(Attendance $attendance): JsonResponse
    {
        $this->verifyTenant($attendance);

        $comments = $attendance->comments()
            ->with('createdBy')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => AttendanceCommentResource::collection($comments)]);
    }

    public function store(Request $request, Attendance $attendance): JsonResponse
    {
        $this->verifyTenant($attendance);

        $validated = $request->validate([
            'body'      => ['required', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        $comment = $attendance->comments()->create([
            'tenant_id'  => app('current.tenant.id'),
            'created_by' => auth()->id(),
            'body'       => $validated['body'],
            'is_public'  => $validated['is_public'] ?? false,
        ]);

        $comment->load('createdBy');

        return response()->json(['data' => new AttendanceCommentResource($comment)], 201);
    }

    public function destroy(Attendance $attendance, AttendanceComment $comment): JsonResponse
    {
        $this->verifyTenant($attendance);

        if ($comment->attendance_id !== $attendance->id) {
            abort(404);
        }

        $user = auth()->user();
        if ($comment->created_by !== $user->id && $user->role !== 'business_owner') {
            abort(403);
        }

        $comment->delete();

        return response()->json(['data' => null]);
    }

    private function verifyTenant(Attendance $attendance): void
    {
        if ($attendance->tenant_id !== app('current.tenant.id')) {
            abort(404);
        }
    }
}
