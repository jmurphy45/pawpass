<?php

namespace App\Http\Controllers\Admin\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $validated = $request->validate([
            'customer_id' => [
                'required',
                'string',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:500'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'line_items.*.item_type' => ['nullable', 'string', 'in:package,service,product,boarding_addon,goodwill,manual'],
            'line_items.*.item_id' => ['nullable', 'string', 'size:26'],
        ]);

        $subtotalCents = array_sum(array_map(
            fn ($li) => $li['quantity'] * $li['unit_price_cents'],
            $validated['line_items']
        ));

        $order = Order::create([
            'id' => Str::ulid(),
            'tenant_id' => $tenantId,
            'customer_id' => $validated['customer_id'],
            'type' => OrderType::Invoice->value,
            'status' => OrderStatus::Pending->value,
            'subtotal_cents' => $subtotalCents,
            'tax_amount_cents' => 0,
            'total_amount' => number_format($subtotalCents / 100, 2, '.', ''),
            'platform_fee_pct' => 0,
            'due_date' => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
        ]);

        foreach ($validated['line_items'] as $i => $li) {
            OrderLineItem::create([
                'id' => Str::ulid(),
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'description' => $li['description'],
                'quantity' => $li['quantity'],
                'unit_price_cents' => $li['unit_price_cents'],
                'sort_order' => $i,
                'item_type' => $li['item_type'] ?? null,
                'item_id' => $li['item_id'] ?? null,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'type' => $order->type->value,
                'status' => $order->status->value,
                'customer_id' => $order->customer_id,
                'total_amount' => number_format((float) $order->total_amount, 2),
                'due_date' => $order->due_date?->toDateString(),
                'invoice_number' => $order->invoice_number,
            ],
        ], 201);
    }

    public function send(Order $order, InvoiceService $invoiceService): JsonResponse
    {
        abort_if($order->tenant_id !== app('current.tenant.id'), 404);

        if (! $order->customer?->email) {
            return response()->json(['error' => 'CUSTOMER_NO_EMAIL'], 422);
        }

        $invoiceService->send($order);

        $order->refresh();

        return response()->json([
            'data' => [
                'invoice_number' => $order->invoice_number,
                'sent_at' => $order->sent_at?->toIso8601String(),
            ],
        ]);
    }
}
