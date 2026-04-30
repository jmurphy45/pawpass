<?php

namespace App\DataTransferObjects\Pims;

readonly class PimsClient
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
    ) {}

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }
}
