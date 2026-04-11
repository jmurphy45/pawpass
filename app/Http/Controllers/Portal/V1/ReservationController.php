<?php

namespace App\Http\Controllers\Portal\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Order;
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
        $customerId = auth()->user()->customer_id;

        $query = Reservation::where('customer_id', $customerId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ReservationResource::collection($query->cursorPaginate(20));
    }

    public function show(string $id): ReservationResource
    {
        $customerId = auth()->user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $reservation->load(['dog', 'kennelUnit', 'reportCards']);

        return new ReservationResource($reservation);
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

        $clientSecret = null;

        if ($request->filled('deposit_amount_cents')) {
            $tenant = Tenant::find($tenantId);

            if ($tenant?->stripe_account_id) {
                $depositCents = (int) $request->deposit_amount_cents;
                $feeCents     = (int) round($depositCents * $tenant->effectivePlatformFeePct($depositCents) / 100);

                $pi = $this->stripe->createHoldPaymentIntent(
                    $depositCents,
                    'usd',
                    $tenant->stripe_account_id,
                    $feeCents,
                    [
                        'reservation_id' => $reservation->id,
                        'tenant_id'      => $tenantId,
                        'dog_name'       => $dog->name,
                    ]
                );

                $order = Order::create([
                    'tenant_id'        => $tenantId,
                    'customer_id'      => $reservation->customer_id,
                    'package_id'       => null,
                    'reservation_id'   => $reservation->id,
                    'type'             => OrderType::Boarding,
                    'status'           => 'pending',
                    'total_amount'     => number_format($depositCents / 100, 2, '.', ''),
                    'platform_fee_pct' => $tenant->effectivePlatformFeePct($depositCents),
                ]);

                $order->payments()->create([
                    'tenant_id'    => $tenantId,
                    'stripe_pi_id' => $pi->id,
                    'amount_cents' => $depositCents,
                    'type'         => PaymentType::Deposit,
                    'status'       => 'pending',
                ]);

                $clientSecret = $pi->client_secret;
            }
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh()), 'client_secret' => $clientSecret], 201);
    }

    public function cancel(string $id): JsonResource|JsonResponse
    {
        $customerId = auth()->user()->customer_id;

        $reservation = Reservation::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($reservation->status !== 'pending') {
            return response()->json(['error' => 'CANNOT_CANCEL_RESERVATION'], 422);
        }

        $updateData = [
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ];

        $depositPayment = $reservation->order?->payments()
            ->where('type', PaymentType::Deposit->value)
            ->whereIn('status', ['pending', 'authorized'])
            ->first();

        if ($depositPayment?->stripe_pi_id) {
            $tenant = Tenant::find($reservation->tenant_id);
            if ($tenant?->stripe_account_id) {
                $this->stripe->cancelPaymentIntent($depositPayment->stripe_pi_id, $tenant->stripe_account_id);

                if ($depositPayment->status === PaymentStatus::Authorized) {
                    $depositPayment->transitionTo(PaymentStatus::Refunded);
                    $depositPayment->update(['refunded_at' => now()]);
                    $reservation->order?->transitionTo(OrderStatus::Refunded);
                } else {
                    $depositPayment->transitionTo(PaymentStatus::Canceled);
                    $reservation->order?->transitionTo(OrderStatus::Canceled);
                }
            }
        }

        $reservation->update($updateData);

        return new ReservationResource($reservation->fresh());
    }
}
