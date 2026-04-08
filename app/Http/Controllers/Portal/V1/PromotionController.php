<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'       => ['required', 'string', 'max:50'],
            'package_id' => ['required', 'string'],
        ]);

        $customer = auth()->user()->customer;
        $package = Package::find($validated['package_id']);

        if (! $package || ! $package->is_active) {
            return response()->json(['data' => ['valid' => false, 'discount_cents' => 0, 'final_cents' => 0, 'message' => 'Package not found.']]);
        }

        $amountCents = (int) round($package->price * 100);

        $result = $this->promotions->validate($validated['code'], $customer, $package, $amountCents);

        return response()->json([
            'data' => [
                'valid'          => $result->valid,
                'discount_cents' => $result->discountCents,
                'final_cents'    => $amountCents - $result->discountCents,
                'message'        => $result->message,
            ],
        ]);
    }
}
