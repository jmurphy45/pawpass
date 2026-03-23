<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Services\KennelAvailabilityService;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController extends Controller
{
    public function __construct(
        private readonly KennelAvailabilityService $availability,
        private readonly VaccinationComplianceService $vaccination,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $customerId = auth()->user()->customer_id;

        $query = Reservation::where('customer_id', $customerId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ReservationResource::collection($query->cursorPaginate(20));
    }

    public function show(string $id): JsonResponse
    {
        $customerId = auth()->user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $reservation->load(['dog', 'kennelUnit', 'reportCards']);

        return response()->json(['data' => new ReservationResource($reservation)]);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');
        $customerId = auth()->user()->customer_id;

        $dog = Dog::findOrFail($request->dog_id);

        if ($dog->customer_id !== $customerId) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $startsAt = now()->parse($request->starts_at);
        $endsAt = now()->parse($request->ends_at);

        $unit = null;

        if ($request->filled('kennel_unit_id')) {
            $unit = KennelUnit::findOrFail($request->kennel_unit_id);

            if (! $this->availability->isAvailable($unit, $startsAt, $endsAt)) {
                return response()->json(['error' => 'UNIT_NOT_AVAILABLE'], 409);
            }
        }

        $violations = $this->vaccination->getViolations($dog, $tenantId);
        if (! empty($violations)) {
            return response()->json([
                'error'      => 'DOG_VACCINATION_INCOMPLETE',
                'violations' => $violations,
            ], 422);
        }

        $reservation = Reservation::create([
            'tenant_id'          => $tenantId,
            'dog_id'             => $dog->id,
            'customer_id'        => $customerId,
            'kennel_unit_id'     => $request->kennel_unit_id,
            'status'             => 'pending',
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'nightly_rate_cents' => $unit?->nightly_rate_cents,
            'notes'              => $request->notes,
            'feeding_schedule'   => $request->feeding_schedule,
            'medication_notes'   => $request->medication_notes,
            'behavioral_notes'   => $request->behavioral_notes,
            'emergency_contact'  => $request->emergency_contact,
            'created_by'         => auth()->id(),
        ]);

        return response()->json(['data' => new ReservationResource($reservation)], 201);
    }

    public function cancel(string $id): JsonResponse
    {
        $customerId = auth()->user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($reservation->status !== 'pending') {
            return response()->json(['error' => 'CANNOT_CANCEL_RESERVATION'], 422);
        }

        $reservation->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }
}
