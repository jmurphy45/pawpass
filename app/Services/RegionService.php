<?php

namespace App\Services;

use Illuminate\Support\Str;
use Squire\Models\Region;

class RegionService
{
    /**
     * Returns US states as [['value' => 'TX', 'label' => 'Texas'], ...].
     */
    public function usStates(): array
    {
        return $this->forCountry('US');
    }

    /**
     * Returns regions for a country as [['value' => 'TX', 'label' => 'Texas'], ...].
     * Returns [] for countries without structured region data (GB, AU, NZ).
     */
    public function forCountry(string $countryCode): array
    {
        $supported = ['US', 'CA'];

        if (! in_array(strtoupper($countryCode), $supported)) {
            return [];
        }

        $lower = strtolower($countryCode);
        $prefix = $lower.'-';

        return Region::where('country_id', $lower)
            ->orderBy('name')
            ->get()
            ->map(fn (Region $region) => [
                'value' => strtoupper(Str::after($region->code, $prefix)),
                'label' => $region->name,
            ])
            ->all();
    }
}
