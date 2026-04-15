<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Models\Breed;
use Illuminate\Http\JsonResponse;

class BreedController extends Controller
{
    public function index(): JsonResponse
    {
        $breeds = Breed::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $breeds]);
    }
}
