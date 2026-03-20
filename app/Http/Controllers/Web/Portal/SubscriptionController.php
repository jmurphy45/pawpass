<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function cancel(string $dogId, Subscription $subscription): RedirectResponse
    {
        $customerId = Auth::user()->customer?->id;

        abort_unless($subscription->customer_id === $customerId, 403);

        if ($subscription->status !== 'active') {
            return redirect()->route('portal.dogs.show', $dogId)
                ->with('error', 'Only active subscriptions can be cancelled.');
        }

        $this->stripe->cancelSubscriptionAtPeriodEnd(
            $subscription->stripe_sub_id,
            $subscription->tenant->stripe_account_id,
        );

        $subscription->update(['cancelled_at' => now()]);

        return redirect()->route('portal.dogs.show', $dogId)
            ->with('success', 'Your subscription has been cancelled and will not renew.');
    }
}
