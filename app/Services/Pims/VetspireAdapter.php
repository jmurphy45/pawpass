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
 * Vetspire adapter — GraphQL API, OAuth2 bearer token.
 *
 * Endpoint:  https://graphql.vetspire.com/
 * Auth:      OAuth2 bearer (practice-issued API key stored as access_token in credentials)
 * Species:   filtered via species: "Canine" in GraphQL query
 * Pagination: cursor-based (endCursor from pageInfo)
 */
class VetspireAdapter implements PimsAdapterInterface
{
    private const ENDPOINT = 'https://graphql.vetspire.com/';

    private const PAGE_SIZE = 50;

    public function providerKey(): string
    {
        return 'vetspire';
    }

    public function providerLabel(): string
    {
        return 'Vetspire';
    }

    public function authenticate(PimsIntegration $integration): void
    {
        // Vetspire uses long-lived practice API keys; no token exchange needed.
        // The api_key stored in credentials['access_token'] is used directly as the bearer token.
        // This method is a no-op but satisfies the interface contract.
    }

    public function testConnection(PimsIntegration $integration): void
    {
        $response = $this->query($integration, '{ __typename }');

        if (isset($response['errors'])) {
            throw new RuntimeException('Vetspire connection test failed: '.json_encode($response['errors']));
        }
    }

    public function fetchClients(PimsIntegration $integration, ?string $cursor = null): array
    {
        $afterClause = $cursor ? ", after: \"{$cursor}\"" : '';

        $gql = <<<GQL
        {
          clients(first: {PAGE_SIZE}{$afterClause}) {
            edges {
              node {
                id
                firstName
                lastName
                email
                phoneNumber
              }
            }
            pageInfo {
              hasNextPage
              endCursor
            }
          }
        }
        GQL;

        $gql = str_replace('{PAGE_SIZE}', self::PAGE_SIZE, $gql);

        $response = $this->query($integration, $gql);
        $this->assertNoErrors($response, 'fetchClients');

        $clients = [];
        foreach ($response['data']['clients']['edges'] ?? [] as $edge) {
            $node = $edge['node'];
            $clients[] = new PimsClient(
                id: $node['id'],
                firstName: $node['firstName'] ?? '',
                lastName: $node['lastName'] ?? '',
                email: $node['email'] ?? null,
                phone: $node['phoneNumber'] ?? null,
            );
        }

        $pageInfo = $response['data']['clients']['pageInfo'] ?? [];
        $nextCursor = ($pageInfo['hasNextPage'] ?? false) ? ($pageInfo['endCursor'] ?? null) : null;

        return ['items' => $clients, 'next_cursor' => $nextCursor];
    }

    public function fetchPatients(PimsIntegration $integration, string $providerClientId): array
    {
        $gql = <<<GQL
        {
          patients(clientId: "{$providerClientId}", species: "Canine", first: 100) {
            edges {
              node {
                id
                name
                breed
                dateOfBirth
                sex
                microchipNumber
              }
            }
          }
        }
        GQL;

        $response = $this->query($integration, $gql);
        $this->assertNoErrors($response, 'fetchPatients');

        $patients = [];
        foreach ($response['data']['patients']['edges'] ?? [] as $edge) {
            $node = $edge['node'];
            $patients[] = new PimsPatient(
                id: $node['id'],
                clientId: $providerClientId,
                name: $node['name'] ?? '',
                breed: $node['breed'] ?? null,
                dob: $node['dateOfBirth'] ?? null,
                sex: $this->normalizeSex($node['sex'] ?? null),
                microchipNumber: $node['microchipNumber'] ?? null,
            );
        }

        return $patients;
    }

    public function fetchVaccinations(PimsIntegration $integration, string $providerPatientId): array
    {
        $gql = <<<GQL
        {
          vaccinationRecords(patientId: "{$providerPatientId}", first: 100) {
            edges {
              node {
                id
                name
                administeredAt
                expiresAt
                administeredBy
              }
            }
          }
        }
        GQL;

        $response = $this->query($integration, $gql);
        $this->assertNoErrors($response, 'fetchVaccinations');

        $vaccinations = [];
        foreach ($response['data']['vaccinationRecords']['edges'] ?? [] as $edge) {
            $node = $edge['node'];
            $vaccinations[] = new PimsVaccination(
                id: $node['id'],
                patientId: $providerPatientId,
                vaccineName: $node['name'] ?? 'Unknown',
                administeredAt: $node['administeredAt'] ?? now()->toDateString(),
                expiresAt: $node['expiresAt'] ?? null,
                administeredBy: $node['administeredBy'] ?? null,
            );
        }

        return $vaccinations;
    }

    private function query(PimsIntegration $integration, string $query): array
    {
        $response = Http::withToken($integration->credentials['access_token'] ?? '')
            ->post(self::ENDPOINT, ['query' => $query]);

        if (! $response->successful()) {
            throw new RuntimeException('Vetspire HTTP error '.$response->status().': '.$response->body());
        }

        return $response->json();
    }

    private function assertNoErrors(array $response, string $context): void
    {
        if (! empty($response['errors'])) {
            throw new RuntimeException("Vetspire {$context} error: ".json_encode($response['errors']));
        }
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
