<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroomingAppointmentResource;
use App\Models\Appointment;
use App\Services\GroomingAppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class GroomingAppointmentController extends Controller
{
    public function __construct(private readonly GroomingAppointmentService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Appointment::ofType('grooming')->with(['groomingDetail', 'dog', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('dog_id')) {
            $query->where('dog_id', $request->dog_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        return GroomingAppointmentResource::collection(
            $query->orderBy('starts_at')->cursorPaginate(20)
        );
    }

    public function store(Request $request): JsonResource|JsonResponse
    {
        $validated = $request->validate([
            'dog_id' => ['required', 'string', 'exists:dogs,id'],
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'service_name' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'duration_mins' => ['nullable', 'integer', 'min:1'],
            'resource_id' => ['nullable', 'string', 'exists:bookable_resources,id'],
            'groomer_user_id' => ['nullable', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $appointment = $this->service->create(array_merge($validated, [
                'tenant_id' => app('current.tenant.id'),
            ]));
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'RESOURCE_NOT_AVAILABLE') {
                return response()->json(['error' => 'RESOURCE_NOT_AVAILABLE'], 409);
            }
            throw $e;
        }

        return new GroomingAppointmentResource($appointment);
    }

    public function show(Appointment $appointment): GroomingAppointmentResource
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        return new GroomingAppointmentResource($appointment->load(['groomingDetail', 'dog', 'customer']));
    }

    public function update(Request $request, Appointment $appointment): JsonResource|JsonResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $validated = $request->validate([
            'service_name' => ['sometimes', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'duration_mins' => ['nullable', 'integer', 'min:1'],
            'resource_id' => ['nullable', 'string', 'exists:bookable_resources,id'],
            'groomer_user_id' => ['nullable', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $appointment = $this->service->update($appointment, $validated);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'RESOURCE_NOT_AVAILABLE') {
                return response()->json(['error' => 'RESOURCE_NOT_AVAILABLE'], 409);
            }
            throw $e;
        }

        return new GroomingAppointmentResource($appointment);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);
        abort_if(
            in_array($appointment->status, ['checked_in', 'checked_out'], true),
            422,
            'Cannot delete an in-progress or completed appointment.'
        );

        $appointment->transitionTo('cancelled', auth()->id());
        $appointment->delete();

        return response()->json(['data' => 'ok']);
    }

    public function confirm(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $appointment->transitionTo('confirmed');

        return response()->json(['data' => ['status' => $appointment->status]]);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $request->validate([
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        $appointment->update(['cancellation_reason' => $request->cancellation_reason]);
        $appointment->transitionTo('cancelled', auth()->id());

        return response()->json(['data' => ['status' => $appointment->status]]);
    }
}
