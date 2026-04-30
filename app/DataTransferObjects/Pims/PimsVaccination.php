<?php

namespace App\DataTransferObjects\Pims;

readonly class PimsVaccination
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $vaccineName,
        public string $administeredAt,
        public ?string $expiresAt,
        public ?string $administeredBy,
    ) {}
}
