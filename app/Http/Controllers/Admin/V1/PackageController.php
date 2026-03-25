<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $packages = Package::get();

        return PackageResource::collection($packages);
    }

    public function store(StorePackageRequest $request): \Illuminate\Http\JsonResponse
    {
        $package = Package::create([
            'tenant_id'                 => app('current.tenant.id'),
            'name'                      => $request->name,
            'description'               => $request->description,
            'type'                      => $request->type,
            'price'                     => $request->price,
            'credit_count'              => $request->credit_count,
            'dog_limit'                 => $request->dog_limit ?? 1,
            'duration_days'             => $request->duration_days,
            'is_active'                 => $request->boolean('is_active', true),
            'is_auto_replenish_eligible' => $request->boolean('is_auto_replenish_eligible', false),
        ]);

        return (new PackageResource($package->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdatePackageRequest $request, Package $package): PackageResource
    {
        $package->update($request->validated());

        return new PackageResource($package->fresh());
    }

    public function archive(string $package): JsonResource|JsonResponse
    {
        $pkg = Package::withTrashed()->findOrFail($package);

        if ($pkg->trashed()) {
            return response()->json(['message' => 'Package is already archived.', 'error_code' => 'ALREADY_ARCHIVED'], 409);
        }

        $pkg->update(['is_active' => false]);
        $pkg->delete();

        return new PackageResource($pkg->fresh());
    }
}
