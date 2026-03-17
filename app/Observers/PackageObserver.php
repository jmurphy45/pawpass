<?php

namespace App\Observers;

use App\Jobs\ArchivePackageFromStripe;
use App\Jobs\SyncPackageToStripe;
use App\Models\Package;

class PackageObserver
{
    public function created(Package $package): void
    {
        if ($package->stripe_product_id === null) {
            SyncPackageToStripe::dispatch($package);
        }
    }

    public function updated(Package $package): void
    {
        $needsSync = $package->stripe_product_id === null
            || $package->wasChanged('price');

        if ($needsSync) {
            SyncPackageToStripe::dispatch($package);
        }
    }

    public function deleted(Package $package): void
    {
        if ($package->stripe_product_id) {
            ArchivePackageFromStripe::dispatch($package);
        }
    }
}
