<?php

namespace App\DataTransferObjects\Pims;

readonly class PimsPatient
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $name,
        public ?string $breed,
        public ?string $dob,
        public ?string $sex,
        public ?string $microchipNumber,
    ) {}
}
