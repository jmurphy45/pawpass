<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    public function refund(Request $request, Order $order): JsonResponse
    {
        if (! in_array($order->status, ['paid', 'partially_refunded'])) {
            return response()->json([
                'message' => 'Only paid orders can be refunded.',
                'error_code' => 'ORDER_NOT_REFUNDABLE',
            ], 409);
        }

        $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
        $this->stripe->createRefund($order->stripe_pi_id, $stripeAccountId);

        DB::transaction(function () use ($order) {
            $order->load(['orderDogs.dog', 'package']);

            foreach ($order->orderDogs as $orderDog) {
                $this->creditService->removeAllOnRefund($order, $orderDog->dog->fresh());
            }

            $order->update(['status' => 'refunded', 'refunded_at' => now()]);
        });

        $order->load(['package', 'orderDogs.dog', 'customer']);

        return response()->json(['data' => new OrderResource($order)]);
    }
}
