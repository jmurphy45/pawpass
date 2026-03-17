<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDogRequest;
use App\Http\Requests\Admin\UpdateDogRequest;
use App\Http\Resources\CreditLedgerResource;
use App\Http\Resources\DogResource;
use App\Models\Customer;
use App\Models\Dog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DogResource::collection(
            Dog::with('customer')->cursorPaginate(20)
        );
    }

    public function store(StoreDogRequest $request): JsonResponse
    {
        $customer = Customer::find($request->customer_id);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        $dog = Dog::create([
            'tenant_id' => app('current.tenant.id'),
            'customer_id' => $customer->id,
            'name' => $request->name,
            'breed' => $request->breed,
            'dob' => $request->dob,
            'sex' => $request->sex,
            'vet_name' => $request->vet_name,
            'vet_phone' => $request->vet_phone,
            'credit_balance' => 0,
        ]);

        return response()->json(['data' => new DogResource($dog)], 201);
    }

    public function show(Dog $dog): JsonResponse
    {
        $ledger = $dog->creditLedger()->orderByDesc('created_at')->limit(10)->get();

        return response()->json([
            'data' => new DogResource($dog),
            'meta' => [
                'recent_ledger' => CreditLedgerResource::collection($ledger),
            ],
        ]);
    }

    public function update(UpdateDogRequest $request, Dog $dog): JsonResponse
    {
        $dog->update($request->only(['name', 'breed', 'dob', 'sex', 'vet_name', 'vet_phone']));

        return response()->json(['data' => new DogResource($dog->fresh())]);
    }

    public function destroy(Dog $dog): JsonResponse
    {
        $dog->delete();

        return response()->json(null, 204);
    }
}
