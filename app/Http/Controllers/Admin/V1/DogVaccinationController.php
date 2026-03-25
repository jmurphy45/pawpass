<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDogVaccinationRequest;
use App\Http\Requests\Admin\UpdateDogVaccinationRequest;
use App\Http\Resources\DogVaccinationResource;
use App\Models\Dog;
use App\Models\DogVaccination;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DogVaccinationController extends Controller
{
    public function index(Dog $dog): AnonymousResourceCollection
    {
        $vaccinations = $dog->vaccinations()->orderBy('administered_at', 'desc')->get();

        return DogVaccinationResource::collection($vaccinations);
    }

    public function store(StoreDogVaccinationRequest $request, Dog $dog): DogVaccinationResource
    {
        $vaccination = DogVaccination::create([
            'tenant_id'       => app('current.tenant.id'),
            'dog_id'          => $dog->id,
            'vaccine_name'    => $request->vaccine_name,
            'administered_at' => $request->administered_at,
            'expires_at'      => $request->expires_at,
            'administered_by' => $request->administered_by,
            'notes'           => $request->notes,
        ]);

        return new DogVaccinationResource($vaccination);
    }

    public function update(UpdateDogVaccinationRequest $request, Dog $dog, DogVaccination $vaccination): DogVaccinationResource
    {
        if ($vaccination->dog_id !== $dog->id) {
            abort(404);
        }

        $vaccination->update($request->only([
            'vaccine_name', 'administered_at', 'expires_at', 'administered_by', 'notes',
        ]));

        return new DogVaccinationResource($vaccination->fresh());
    }

    public function destroy(Dog $dog, DogVaccination $vaccination): DogVaccinationResource
    {
        if ($vaccination->dog_id !== $dog->id) {
            abort(404);
        }

        $resource = new DogVaccinationResource($vaccination);
        $vaccination->delete();

        return $resource;
    }
}
