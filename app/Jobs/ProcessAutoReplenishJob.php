<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\AutoReplenishService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAutoReplenishJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $dogId)
    {
        $this->onQueue('default');
    }

    public function handle(AutoReplenishService $service): void
    {
        $dog = Dog::allTenants()->find($this->dogId);

        if (! $dog) {
            return;
        }

        $service->trigger($dog);
    }
}
