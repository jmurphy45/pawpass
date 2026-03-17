<?php

namespace Tests\Traits;

use App\Auth\JwtService;
use App\Models\User;

trait InteractsWithJwt
{
    protected JwtService $jwtService;

    protected function setUpJwt(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKey);
        $publicKey = openssl_pkey_get_details($keyPair)['key'];

        $this->jwtService = new JwtService($privateKey, $publicKey);

        app()->instance(JwtService::class, $this->jwtService);
    }

    protected function jwtFor(User $user): string
    {
        return $this->jwtService->issue($user);
    }
}
