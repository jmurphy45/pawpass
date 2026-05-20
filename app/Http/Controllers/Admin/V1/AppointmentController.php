<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Dog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Appointment::with(['dog', 'customer', 'bookableResource'])
            ->orderBy('starts_at');

        if ($request->filled('service_type')) {
            $query->ofType($request->service_type);
        }

        if ($request->filled('dog_id')) {
            $query->forDog($request->dog_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        return AppointmentResource::collection($query->cursorPaginate(20));
    }

    public function schedule(Request $request, Dog $dog): AnonymousResourceCollection
    {
        $query = Appointment::forDog($dog->id)
            ->upcoming()
            ->with(['bookableResource'])
            ->orderBy('starts_at');

        return AppointmentResource::collection($query->paginate(20));
    }
}
