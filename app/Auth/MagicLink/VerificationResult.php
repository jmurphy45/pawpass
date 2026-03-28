<?php

namespace App\Auth\MagicLink;

use App\Models\User;

readonly class VerificationResult
{
    public function __construct(
        public Action $action,
        public ?User $user,
        public int $riskScore,
        public array $riskFactors,
        public float $fpSimilarityScore,
    ) {}

    /**
     * Create a failure result (token not found, expired, already used).
     */
    public static function failure(): self
    {
        return new self(
            action: Action::BLOCK,
            user: null,
            riskScore: 99,
            riskFactors: ['invalid_token'],
            fpSimilarityScore: 0.0,
        );
    }
}
