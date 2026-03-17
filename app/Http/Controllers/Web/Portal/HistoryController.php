<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $orders = Order::where('customer_id', $customer->id)
            ->with(['package', 'orderDogs.dog'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Portal/History', [
            'orders' => [
                'data' => collect($orders->items())->map(fn ($o) => [
                    'id'           => $o->id,
                    'package_name' => $o->package?->name ?? 'Unknown',
                    'dog_names'    => $o->orderDogs->map(fn ($od) => $od->dog?->name)->filter()->values(),
                    'amount_cents' => (int) round((float) $o->total_amount * 100),
                    'status'       => $o->status,
                    'created_at'   => $o->created_at->toIso8601String(),
                ]),
                'meta' => [
                    'total'        => $orders->total(),
                    'per_page'     => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                ],
            ],
        ]);
    }
}
