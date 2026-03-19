<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Jobs\SyncPackageToStripe;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $packages = Package::get();

        return PackageResource::collection($packages);
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = Package::create([
            'tenant_id'    => app('current.tenant.id'),
            'name'         => $request->name,
            'description'  => $request->description,
            'type'         => $request->type,
            'price'        => $request->price,
            'credit_count' => $request->credit_count,
            'dog_limit'    => $request->dog_limit ?? 1,
            'duration_days' => $request->duration_days,
            'is_active'    => $request->boolean('is_active', true),
        ]);

        return response()->json(['data' => new PackageResource($package->fresh())], 201);
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $recurringChanged = $request->has('is_recurring_enabled') || $request->has('recurring_interval_days');

        $package->update($request->validated());

        if ($recurringChanged) {
            SyncPackageToStripe::dispatch($package->fresh());
        }

        return response()->json(['data' => new PackageResource($package->fresh())]);
    }

    public function archive(string $package): JsonResponse
    {
        $pkg = Package::withTrashed()->findOrFail($package);

        if ($pkg->trashed()) {
            return response()->json(['message' => 'Package is already archived.', 'error_code' => 'ALREADY_ARCHIVED'], 409);
        }

        $pkg->update(['is_active' => false]);
        $pkg->delete();

        return response()->json(['data' => new PackageResource($pkg->fresh())]);
    }
}
