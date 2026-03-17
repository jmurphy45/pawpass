<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $request->user()->customer;
        $dogIds = $customer->dogs()->pluck('id');

        $attendances = Attendance::whereIn('dog_id', $dogIds)
            ->latest('checked_in_at')
            ->paginate(20);

        return AttendanceResource::collection($attendances);
    }
}
