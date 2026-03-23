<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVaccinationRequirementRequest;
use App\Http\Resources\VaccinationRequirementResource;
use App\Models\VaccinationRequirement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VaccinationRequirementController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return VaccinationRequirementResource::collection(
            VaccinationRequirement::orderBy('vaccine_name')->get()
        );
    }

    public function store(StoreVaccinationRequirementRequest $request): JsonResponse
    {
        try {
            $requirement = VaccinationRequirement::create([
                'tenant_id'    => app('current.tenant.id'),
                'vaccine_name' => $request->vaccine_name,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['error' => 'VACCINE_REQUIREMENT_ALREADY_EXISTS'], 409);
        }

        return response()->json(['data' => new VaccinationRequirementResource($requirement)], 201);
    }

    public function destroy(VaccinationRequirement $vaccinationRequirement): JsonResponse
    {
        $resource = new VaccinationRequirementResource($vaccinationRequirement);
        $vaccinationRequirement->delete();

        return response()->json(['data' => $resource]);
    }
}
