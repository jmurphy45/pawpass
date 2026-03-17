<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreDogRequest;
use App\Http\Requests\Portal\UpdateDogRequest;
use App\Http\Resources\CreditLedgerResource;
use App\Http\Resources\DogResource;
use App\Models\Dog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $customer = auth()->user()->customer;

        if (! $customer) {
            abort(404, 'Customer not found.');
        }

        return DogResource::collection($customer->dogs()->get());
    }

    public function store(StoreDogRequest $request): JsonResponse
    {
        $customer = auth()->user()->customer;

        if (! $customer) {
            abort(404, 'Customer not found.');
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
        $customer = auth()->user()->customer;

        if (! $customer || $dog->customer_id !== $customer->id) {
            abort(404);
        }

        return response()->json(['data' => new DogResource($dog)]);
    }

    public function update(UpdateDogRequest $request, Dog $dog): JsonResponse
    {
        $customer = auth()->user()->customer;

        if (! $customer || $dog->customer_id !== $customer->id) {
            abort(403);
        }

        $dog->update($request->only(['name', 'breed', 'dob', 'sex', 'vet_name', 'vet_phone']));

        return response()->json(['data' => new DogResource($dog->fresh())]);
    }

    public function credits(Dog $dog): AnonymousResourceCollection
    {
        $customer = auth()->user()->customer;

        if (! $customer || $dog->customer_id !== $customer->id) {
            abort(404);
        }

        $entries = $dog->creditLedger()->orderByDesc('created_at')->cursorPaginate(20);

        return CreditLedgerResource::collection($entries);
    }
}
