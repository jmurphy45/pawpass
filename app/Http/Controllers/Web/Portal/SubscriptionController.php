<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function cancel(string $dogId, Subscription $subscription): RedirectResponse
    {
        $customerId = Auth::user()->customer?->id;

        abort_unless($subscription->customer_id === $customerId, 403);

        if ($subscription->status !== 'active') {
            return redirect()->route('portal.dogs.show', $dogId)
                ->with('error', 'Only active subscriptions can be cancelled.');
        }

        $subscription->update(['cancelled_at' => now(), 'status' => 'cancelled']);

        $subscription->dog?->update(['auto_replenish_enabled' => false]);

        return redirect()->route('portal.dogs.show', $dogId)
            ->with('success', 'Your plan has been cancelled and will not auto-renew.');
    }
}
