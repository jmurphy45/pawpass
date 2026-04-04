<?php

namespace App\Services;

use App\Models\Dog;
use App\Models\VaccinationRequirement;

class VaccinationComplianceService
{
    /**
     * Returns true if the dog has a valid (non-expired) vaccination
     * for every vaccine required by the tenant.
     */
    public function isCompliant(Dog $dog, string $tenantId): bool
    {
        return empty($this->getViolations($dog, $tenantId));
    }

    /**
     * Returns per-vaccine compliance status for the UI.
     * Each entry: ['vaccine_name' => string, 'status' => 'valid'|'missing'|'expired']
     */
    public function getVaccinationStatus(Dog $dog, string $tenantId): array
    {
        $requirements = VaccinationRequirement::where('tenant_id', $tenantId)->get();

        if ($requirements->isEmpty()) {
            return [];
        }

        $vaccinations = $dog->vaccinations()->get()->keyBy(fn ($v) => strtolower(trim($v->vaccine_name)));

        return $requirements->map(function ($req) use ($vaccinations) {
            $key = strtolower(trim($req->vaccine_name));
            $vax = $vaccinations->get($key);

            $status = match (true) {
                $vax === null              => 'missing',
                $vax->isExpired()          => 'expired',
                default                    => 'valid',
            };

            $expiresAt      = $vax?->expires_at?->toDateString();
            $daysRemaining  = $vax?->expires_at ? (int) now()->diffInDays($vax->expires_at, false) : null;

            return [
                'vaccine_name'   => $req->vaccine_name,
                'status'         => $status,
                'expires_at'     => $expiresAt,
                'days_remaining' => $daysRemaining,
            ];
        })->values()->all();
    }

    /**
     * Returns an array of vaccine names that the dog is missing or has expired.
     * An empty array means fully compliant.
     */
    public function getViolations(Dog $dog, string $tenantId): array
    {
        $required = VaccinationRequirement::where('tenant_id', $tenantId)
            ->pluck('vaccine_name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->all();

        if (empty($required)) {
            return [];
        }

        $validVaccines = $dog->vaccinations()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->pluck('vaccine_name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->all();

        $violations = [];
        foreach ($required as $req) {
            if (! in_array($req, $validVaccines)) {
                $violations[] = $req;
            }
        }

        return $violations;
    }
}
