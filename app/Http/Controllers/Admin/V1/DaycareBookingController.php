<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DaycareBookingResource;
use App\Models\Appointment;
use App\Services\DaycareBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class DaycareBookingController extends Controller
{
    public function __construct(private readonly DaycareBookingService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Appointment::ofType('daycare_booking')->with(['daycareBookingDetail', 'dog', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('dog_id')) {
            $query->where('dog_id', $request->dog_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        return DaycareBookingResource::collection(
            $query->orderBy('starts_at')->cursorPaginate(20)
        );
    }

    public function store(Request $request): JsonResource|JsonResponse
    {
        $validated = $request->validate([
            'dog_id' => ['required', 'string', 'exists:dogs,id'],
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
            'drop_off_window_start' => ['nullable', 'date_format:H:i'],
            'drop_off_window_end' => ['nullable', 'date_format:H:i'],
        ]);

        try {
            $appointment = $this->service->create(array_merge($validated, [
                'tenant_id' => app('current.tenant.id'),
            ]));
        } catch (\App\Exceptions\InsufficientCreditsException) {
            return response()->json(['error' => 'INSUFFICIENT_CREDITS'], 422);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'CAPACITY_FULL') {
                return response()->json(['error' => 'CAPACITY_FULL'], 409);
            }
            throw $e;
        }

        return new DaycareBookingResource($appointment);
    }

    public function show(Appointment $appointment): DaycareBookingResource
    {
        abort_if($appointment->service_type !== 'daycare_booking', 404);

        return new DaycareBookingResource($appointment->load(['daycareBookingDetail', 'dog', 'customer']));
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'daycare_booking', 404);

        if ($appointment->canTransitionTo('cancelled')) {
            $dog = $appointment->dog;
            if ($dog) {
                app(\App\Services\DogCreditService::class)->releaseDaycareHold($dog, $appointment);
            }
            $appointment->transitionTo('cancelled', auth()->id());
        }

        $appointment->delete();

        return response()->json(['data' => 'ok']);
    }

    public function confirm(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'daycare_booking', 404);

        $appointment->transitionTo('confirmed');

        return response()->json(['data' => ['status' => $appointment->status]]);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->service_type !== 'daycare_booking', 404);

        $request->validate(['cancellation_reason' => ['nullable', 'string']]);

        $dog = $appointment->dog;
        if ($dog) {
            app(\App\Services\DogCreditService::class)->releaseDaycareHold($dog, $appointment);
        }

        $appointment->update(['cancellation_reason' => $request->cancellation_reason]);
        $appointment->transitionTo('cancelled', auth()->id());

        return response()->json(['data' => ['status' => $appointment->status]]);
    }
}
