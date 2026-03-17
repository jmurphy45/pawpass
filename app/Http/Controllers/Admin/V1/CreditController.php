<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectionCreditRequest;
use App\Http\Requests\Admin\GoodwillCreditRequest;
use App\Http\Requests\Admin\TransferCreditRequest;
use App\Models\Dog;
use App\Services\DogCreditService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CreditController extends Controller
{
    public function __construct(private DogCreditService $credits) {}

    public function goodwill(GoodwillCreditRequest $request, Dog $dog): JsonResponse
    {
        $this->credits->addGoodwill($dog, $request->credits, $request->note, auth()->user());

        $dog->refresh();

        return response()->json(['data' => ['credit_balance' => $dog->credit_balance]]);
    }

    public function correction(CorrectionCreditRequest $request, Dog $dog): JsonResponse
    {
        $this->credits->applyCorrection($dog, $request->delta, $request->note, auth()->user());

        $dog->refresh();

        return response()->json(['data' => ['credit_balance' => $dog->credit_balance]]);
    }

    public function transfer(TransferCreditRequest $request, Dog $dog): JsonResponse
    {
        $toDog = Dog::find($request->to_dog_id);

        if (! $toDog) {
            return response()->json(['message' => 'Target dog not found.'], 404);
        }

        try {
            $this->credits->transfer($dog, $toDog, $request->credits);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error_code' => 'CROSS_CUSTOMER_TRANSFER'], 409);
        }

        $dog->refresh();
        $toDog->refresh();

        return response()->json(['data' => [
            'from_balance' => $dog->credit_balance,
            'to_balance' => $toDog->credit_balance,
        ]]);
    }
}
