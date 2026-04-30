<?php

namespace App\Services\Pims;

use App\Contracts\PimsAdapterInterface;
use App\DataTransferObjects\Pims\PimsClient;
use App\DataTransferObjects\Pims\PimsPatient;
use App\DataTransferObjects\Pims\PimsVaccination;
use App\Models\PimsIntegration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ezyVet adapter — REST API v1, OAuth2 client credentials.
 *
 * Base URL:  https://{subdomain}.ezyvet.com/api/v1
 * Auth:      POST /oauth/access_token  (client_credentials grant)
 * Rate:      ~2 req/sec per token
 * Species:   fetched once per sync; dog species_id used to filter /animal requests
 * Docs:      https://apisandbox.ezyvet.com/
 */
class EzyVetAdapter implements PimsAdapterInterface
{
    private const PAGE_SIZE = 100;

    public function providerKey(): string
    {
        return 'ezyvet';
    }

    public function providerLabel(): string
    {
        return 'ezyVet';
    }

    public function authenticate(PimsIntegration $integration): void
    {
        $creds = $integration->credentials;
        $baseUrl = rtrim($integration->api_base_url, '/');

        $response = Http::asForm()->post("{$baseUrl}/oauth/access_token", [
            'grant_type' => 'client_credentials',
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'scope' => 'read-only',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('ezyVet authentication failed: '.$response->body());
        }

        $data = $response->json();

        $integration->credentials = array_merge($creds, [
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600)->toIso8601String(),
        ]);
        $integration->save();
    }

    public function testConnection(PimsIntegration $integration): void
    {
        $this->ensureToken($integration);

        $baseUrl = rtrim($integration->api_base_url, '/');
        $response = Http::withToken($integration->credentials['access_token'])
            ->get("{$baseUrl}/species", ['limit' => 1]);

        if (! $response->successful()) {
            throw new RuntimeException('ezyVet connection test failed: '.$response->body());
        }
    }

    public function fetchClients(PimsIntegration $integration, ?string $cursor = null): array
    {
        $this->ensureToken($integration);

        $baseUrl = rtrim($integration->api_base_url, '/');
        $offset = (int) ($cursor ?? 0);

        $response = Http::withToken($integration->credentials['access_token'])
            ->get("{$baseUrl}/contact", [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'active' => 1,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ezyVet fetchClients failed: '.$response->body());
        }

        $items = $response->json('items', []);
        $clients = [];

        foreach ($items as $item) {
            $contact = $item['contact'] ?? $item;
            $clients[] = new PimsClient(
                id: (string) $contact['id'],
                firstName: $contact['first_name'] ?? '',
                lastName: $contact['last_name'] ?? '',
                email: $contact['email'] ?? null,
                phone: $contact['phone'] ?? ($contact['phone_numbers'][0]['number'] ?? null),
            );
        }

        $nextCursor = count($items) === self::PAGE_SIZE
            ? (string) ($offset + self::PAGE_SIZE)
            : null;

        return ['items' => $clients, 'next_cursor' => $nextCursor];
    }

    public function fetchPatients(PimsIntegration $integration, string $providerClientId): array
    {
        $this->ensureToken($integration);

        $baseUrl = rtrim($integration->api_base_url, '/');
        $dogSpeciesId = $this->resolveDogSpeciesId($integration, $baseUrl);

        $response = Http::withToken($integration->credentials['access_token'])
            ->get("{$baseUrl}/animal", [
                'contact_id' => $providerClientId,
                'species_id' => $dogSpeciesId,
                'active' => 1,
                'limit' => 100,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ezyVet fetchPatients failed: '.$response->body());
        }

        $patients = [];
        foreach ($response->json('items', []) as $item) {
            $animal = $item['animal'] ?? $item;
            $patients[] = new PimsPatient(
                id: (string) $animal['id'],
                clientId: $providerClientId,
                name: $animal['name'] ?? '',
                breed: $animal['breed'] ?? null,
                dob: $animal['date_of_birth'] ?? null,
                sex: $this->normalizeSex($animal['sex'] ?? null),
                microchipNumber: $animal['microchip_number'] ?? null,
            );
        }

        return $patients;
    }

    public function fetchVaccinations(PimsIntegration $integration, string $providerPatientId): array
    {
        $this->ensureToken($integration);

        $baseUrl = rtrim($integration->api_base_url, '/');

        $response = Http::withToken($integration->credentials['access_token'])
            ->get("{$baseUrl}/animalVaccine", [
                'animal_id' => $providerPatientId,
                'limit' => 100,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ezyVet fetchVaccinations failed: '.$response->body());
        }

        $vaccinations = [];
        foreach ($response->json('items', []) as $item) {
            $vax = $item['animalVaccine'] ?? $item;
            $vaccinations[] = new PimsVaccination(
                id: (string) $vax['id'],
                patientId: $providerPatientId,
                vaccineName: $vax['description'] ?? 'Unknown',
                administeredAt: $vax['administered_date'] ?? now()->toDateString(),
                expiresAt: $vax['expiry_date'] ?? null,
                administeredBy: $vax['administered_by'] ?? null,
            );
        }

        return $vaccinations;
    }

    private function ensureToken(PimsIntegration $integration): void
    {
        $creds = $integration->credentials;
        $expiresAt = $creds['token_expires_at'] ?? null;

        if (! $expiresAt || now()->addMinutes(5)->isAfter($expiresAt)) {
            $this->authenticate($integration);
        }
    }

    private function resolveDogSpeciesId(PimsIntegration $integration, string $baseUrl): ?int
    {
        // Cache the species ID in credentials to avoid repeated lookups.
        if (isset($integration->credentials['dog_species_id'])) {
            return (int) $integration->credentials['dog_species_id'];
        }

        $response = Http::withToken($integration->credentials['access_token'])
            ->get("{$baseUrl}/species", ['limit' => 100]);

        if (! $response->successful()) {
            return null;
        }

        foreach ($response->json('items', []) as $item) {
            $species = $item['species'] ?? $item;
            if (stripos($species['name'] ?? '', 'canine') !== false || stripos($species['name'] ?? '', 'dog') !== false) {
                $speciesId = (int) $species['id'];
                $integration->credentials = array_merge($integration->credentials, ['dog_species_id' => $speciesId]);
                $integration->save();

                return $speciesId;
            }
        }

        return null;
    }

    private function normalizeSex(?string $sex): ?string
    {
        if ($sex === null) {
            return null;
        }

        $lower = strtolower($sex);

        if (str_starts_with($lower, 'm')) {
            return 'male';
        }
        if (str_starts_with($lower, 'f')) {
            return 'female';
        }

        return 'unknown';
    }
}
