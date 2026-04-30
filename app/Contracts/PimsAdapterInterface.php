<?php

namespace App\Contracts;

use App\DataTransferObjects\Pims\PimsClient;
use App\DataTransferObjects\Pims\PimsPatient;
use App\DataTransferObjects\Pims\PimsVaccination;
use App\Models\PimsIntegration;

interface PimsAdapterInterface
{
    public function providerKey(): string;

    public function providerLabel(): string;

    /**
     * Obtain or refresh the access token, persisting updated credentials back to $integration.
     */
    public function authenticate(PimsIntegration $integration): void;

    /**
     * Verify credentials work without running a full sync. Throws \RuntimeException on failure.
     */
    public function testConnection(PimsIntegration $integration): void;

    /**
     * Fetch one page of clients (dog owners).
     * $cursor is the opaque cursor returned by the previous call.
     *
     * @return array{items: PimsClient[], next_cursor: string|null}
     */
    public function fetchClients(PimsIntegration $integration, ?string $cursor = null): array;

    /**
     * Fetch dog patients for the given provider client ID.
     * Adapters are responsible for filtering to canine species only.
     *
     * @return PimsPatient[]
     */
    public function fetchPatients(PimsIntegration $integration, string $providerClientId): array;

    /**
     * Fetch vaccination records for the given provider patient ID.
     *
     * @return PimsVaccination[]
     */
    public function fetchVaccinations(PimsIntegration $integration, string $providerPatientId): array;
}
