<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Dog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AutoReplenishController extends Controller
{
    public function cancel(Dog $dog): RedirectResponse
    {
        $customerId = Auth::user()->customer?->id;
        abort_unless($dog->customer_id === $customerId, 403);

        $dog->update([
            'auto_replenish_enabled'    => false,
            'auto_replenish_package_id' => null,
        ]);

        return redirect()->route('portal.dogs.show', $dog->id)
            ->with('success', 'Auto-replenish has been disabled.');
    }
}
