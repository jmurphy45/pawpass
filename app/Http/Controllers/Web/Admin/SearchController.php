<?php

namespace App\Http\Controllers\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        $customers = [];
        $dogs = [];

        if ($search) {
            $term = '%'.strtolower($search).'%';

            $customers = Customer::query()
                ->select('id', 'name', 'email', 'phone')
                ->where(fn ($q) => $q
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$term])
                )
                ->limit(10)
                ->get()
                ->values();

            $dogs = Dog::query()
                ->join('customers', 'dogs.customer_id', '=', 'customers.id')
                ->whereNull('customers.deleted_at')
                ->select('dogs.id', 'dogs.name', 'customers.name as customer_name', 'dogs.credit_balance')
                ->where(fn ($q) => $q
                    ->whereRaw('LOWER(dogs.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(customers.name) LIKE ?', [$term])
                )
                ->limit(10)
                ->get()
                ->map(fn ($dog) => [
                    'id' => $dog->id,
                    'name' => $dog->name,
                    'customer_name' => $dog->customer_name,
                    'credit_balance' => $dog->credit_balance,
                ]);
        }

        return response()->json(['customers' => $customers, 'dogs' => $dogs]);
    }
}
