<?php

namespace App\Services\Pims;

use App\Contracts\PimsAdapterInterface;
use RuntimeException;

class PimsAdapterRegistry
{
    /** @var array<string, PimsAdapterInterface> */
    private array $adapters = [];

    public function register(PimsAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->providerKey()] = $adapter;
    }

    public function for(string $providerKey): PimsAdapterInterface
    {
        if (! isset($this->adapters[$providerKey])) {
            throw new RuntimeException("No PIMS adapter registered for provider '{$providerKey}'.");
        }

        return $this->adapters[$providerKey];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function providers(): array
    {
        return array_values(array_map(
            fn (PimsAdapterInterface $adapter) => [
                'key' => $adapter->providerKey(),
                'label' => $adapter->providerLabel(),
            ],
            $this->adapters,
        ));
    }
}
