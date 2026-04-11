<?php

namespace App\Http\Controllers\Admin\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReservationRequest;
use App\Http\Requests\Admin\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\KennelAvailabilityService;
use App\Services\StripeService;
use App\Services\VaccinationComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationController extends Controller
{
    public function __construct(
        private readonly KennelAvailabilityService $availability,
        private readonly VaccinationComplianceService $vaccination,
        private readonly StripeService $stripe,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Reservation::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('dog_id')) {
            $query->where('dog_id', $request->dog_id);
        }

        if ($request->filled('date')) {
            $date = $request->date;
            $query->where('starts_at', '<=', $date)
                ->where('ends_at', '>', $date);
        }

        return ReservationResource::collection($query->cursorPaginate(20));
    }

    public function store(StoreReservationRequest $request): JsonResource|JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $dog = Dog::findOrFail($request->dog_id);

        $startsAt = now()->parse($request->starts_at);
        $endsAt = now()->parse($request->ends_at);

        $unit = null;

        if ($request->filled('kennel_unit_id')) {
            $unit = KennelUnit::findOrFail($request->kennel_unit_id);

            if (! $this->availability->isAvailable($unit, $startsAt, $endsAt)) {
                return response()->json(['error' => 'UNIT_NOT_AVAILABLE'], 409);
            }
        }

        if (! $request->boolean('ignore_vaccination_check')) {
            $violations = $this->vaccination->getViolations($dog, $tenantId);
            if (! empty($violations)) {
                return response()->json([
                    'error'      => 'DOG_VACCINATION_INCOMPLETE',
                    'violations' => $violations,
                ], 422);
            }
        }

        $nightlyRate = $request->filled('nightly_rate_cents')
            ? $request->nightly_rate_cents
            : $unit?->nightly_rate_cents;

        $reservation = Reservation::create([
            'tenant_id'          => $tenantId,
            'dog_id'             => $dog->id,
            'customer_id'        => $dog->customer_id,
            'kennel_unit_id'     => $request->kennel_unit_id,
            'status'             => 'pending',
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'nightly_rate_cents' => $nightlyRate,
            'notes'              => $request->notes,
            'created_by'         => auth()->id(),
        ]);

        return new ReservationResource($reservation);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $reservation->load(['dog', 'kennelUnit']);

        return new ReservationResource($reservation);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): JsonResource|JsonResponse
    {
        $startsAt = $request->filled('starts_at') ? now()->parse($request->starts_at) : $reservation->starts_at;
        $endsAt = $request->filled('ends_at') ? now()->parse($request->ends_at) : $reservation->ends_at;

        $unitId = $request->has('kennel_unit_id') ? $request->kennel_unit_id : $reservation->kennel_unit_id;

        if ($unitId) {
            $unit = KennelUnit::findOrFail($unitId);

            if (! $this->availability->isAvailable($unit, $startsAt, $endsAt, $reservation->id)) {
                return response()->json(['error' => 'UNIT_NOT_AVAILABLE'], 409);
            }
        }

        $fields = $request->only([
            'kennel_unit_id', 'starts_at', 'ends_at', 'nightly_rate_cents',
            'notes', 'feeding_schedule', 'medication_notes', 'behavioral_notes', 'emergency_contact',
        ]);

        if ($fields) {
            $reservation->update($fields);
        }

        $newStatus = $request->input('status');

        if ($newStatus && $newStatus !== $reservation->status) {
            if (! $reservation->canTransitionTo($newStatus)) {
                return response()->json(['error' => 'INVALID_STATUS_TRANSITION'], 422);
            }
            $reservation->transitionTo($newStatus, auth()->id());
        }

        $depositPayment = $reservation->order?->payments()
            ->where('type', PaymentType::Deposit->value)
            ->whereIn('status', ['pending', 'authorized'])
            ->first();

        if ($depositPayment?->stripe_pi_id) {
            $tenant          = Tenant::find($reservation->tenant_id);
            $stripeAccountId = $tenant?->stripe_account_id;

            if ($stripeAccountId) {
                if ($newStatus === 'checked_in' && $depositPayment->status !== PaymentStatus::Paid) {
                    $this->stripe->capturePaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->transitionTo(PaymentStatus::Paid);
                    $depositPayment->update(['paid_at' => now()]);
                } elseif ($newStatus === 'cancelled' && $depositPayment->status === PaymentStatus::Authorized) {
                    $this->stripe->cancelPaymentIntent($depositPayment->stripe_pi_id, $stripeAccountId);
                    $depositPayment->transitionTo(PaymentStatus::Refunded);
                    $depositPayment->update(['refunded_at' => now()]);
                    $reservation->order?->transitionTo(OrderStatus::Refunded);
                }
            }
        }

        return new ReservationResource($reservation->fresh());
    }

    public function destroy(Reservation $reservation): JsonResource|JsonResponse
    {
        if (! in_array($reservation->status, ['pending', 'cancelled'])) {
            return response()->json(['error' => 'CANNOT_DELETE_ACTIVE_RESERVATION'], 409);
        }

        $resource = new ReservationResource($reservation);
        $reservation->delete();

        return $resource;
    }
}
