<?php

namespace App\Http\Controllers\Admin\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly DogCreditService $creditService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::with(['customer', 'package'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return OrderResource::collection($query->paginate(20));
    }

    public function refund(Request $request, Order $order): JsonResource|JsonResponse
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded])) {
            return response()->json([
                'message' => 'Only paid orders can be refunded.',
                'error_code' => 'ORDER_NOT_REFUNDABLE',
            ], 409);
        }

        $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
        $payment = $order->payments()->whereIn('status', ['paid', 'authorized'])->latest()->first();

        if ($payment?->stripe_pi_id) {
            try {
                $this->stripe->createRefund($payment->stripe_pi_id, $stripeAccountId);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }
        }

        DB::transaction(function () use ($order, $payment) {
            $order->load(['orderDogs.dog', 'package']);

            foreach ($order->orderDogs as $orderDog) {
                $this->creditService->removeAllOnRefund($order, $orderDog->dog->fresh());
            }

            if ($payment) {
                $payment->transitionTo(PaymentStatus::Refunded);
                $payment->update(['refunded_at' => now()]);
            }
            $order->transitionTo(OrderStatus::Refunded);
        });

        $order->load(['package', 'orderDogs.dog', 'customer']);

        return new OrderResource($order);
    }
}
