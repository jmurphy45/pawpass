<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly DogCreditService $creditService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Order::with(['customer', 'package'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->through(fn ($order) => [
            'id'           => $order->id,
            'customer_name' => $order->customer?->name,
            'package_name' => $order->package?->name,
            'total_amount' => $order->total_amount,
            'status'       => $order->status,
            'created_at'   => $order->created_at->toIso8601String(),
            'refunded_at'  => $order->refunded_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/Payments/Index', [
            'orders'  => $orders,
            'filters' => ['status' => $request->query('status', '')],
        ]);
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        if (! in_array($order->status, ['paid', 'partially_refunded'])) {
            return back()->with('error', 'Only paid orders can be refunded.');
        }

        try {
            $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
            $this->stripe->createRefund($order->stripe_pi_id, $stripeAccountId);

            DB::transaction(function () use ($order) {
                $order->load(['orderDogs.dog', 'package']);

                foreach ($order->orderDogs as $orderDog) {
                    $this->creditService->removeAllOnRefund($order, $orderDog->dog->fresh());
                }

                $order->update(['status' => 'refunded', 'refunded_at' => now()]);
            });

            return back()->with('success', 'Order refunded successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }
    }
}
