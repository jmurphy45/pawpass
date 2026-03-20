<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class OrderReceiptController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        $customer = Auth::user()->customer;
        abort_if($order->customer_id !== $customer->id, 403);
        abort_if($order->status !== 'paid' || !$order->stripe_pi_id, 404);

        $url = app(StripeService::class)->retrieveReceiptUrl(
            $order->stripe_pi_id,
            $order->tenant->stripe_account_id
        );

        abort_if(!$url, 404);

        return redirect()->away($url);
    }
}
