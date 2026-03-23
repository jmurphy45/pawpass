<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttendanceAddonRequest;
use App\Http\Resources\AttendanceAddonResource;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use App\Models\AddonType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceAddonController extends Controller
{
    public function index(Attendance $attendance): AnonymousResourceCollection
    {
        return AttendanceAddonResource::collection(
            $attendance->addons()->with('addonType')->get()
        );
    }

    public function store(StoreAttendanceAddonRequest $request, Attendance $attendance): JsonResponse
    {
        $addonType = AddonType::find($request->addon_type_id);

        if (! $addonType || ! $addonType->appliesToDaycare()) {
            abort(404);
        }

        $addon = $attendance->addons()->create([
            'addon_type_id'    => $addonType->id,
            'quantity'         => $request->quantity ?? 1,
            'unit_price_cents' => $addonType->price_cents,
            'note'             => $request->note,
        ]);

        return response()->json(['data' => new AttendanceAddonResource($addon->load('addonType'))], 201);
    }

    public function destroy(Attendance $attendance, AttendanceAddon $addon): JsonResponse
    {
        if ($addon->attendance_id !== $attendance->id) {
            abort(404);
        }

        $resource = new AttendanceAddonResource($addon);
        $addon->delete();

        return response()->json(['data' => $resource]);
    }
}
