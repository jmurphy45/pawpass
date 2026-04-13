<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\AutoReplenishService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAutoReplenishJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $dogId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->dogId;
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
