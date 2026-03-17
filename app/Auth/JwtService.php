<?php

namespace App\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use stdClass;

class JwtService
{
    private string $algorithm = 'RS256';

    public function __construct(
        private string $privateKey,
        private string $publicKey,
    ) {}

    public function issue(User $user): string
    {
        $now = time();

        $payload = [
            'sub' => $user->id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + 900,
        ];

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->publicKey, $this->algorithm));
    }

    public function issueRefreshToken(User $user): string
    {
        $user->tokens()->where('name', 'refresh')->delete();

        $token = $user->createToken('refresh', ['*'], now()->addDays(30));

        return $token->plainTextToken;
    }

    public function rotateRefreshToken(string $token): array
    {
        [$id, $plainText] = array_pad(explode('|', $token, 2), 2, null);

        if (! $id || ! $plainText || ! ctype_digit($id)) {
            throw new InvalidArgumentException('Invalid refresh token format.');
        }

        $accessToken = PersonalAccessToken::find($id);

        if (! $accessToken || ! hash_equals($accessToken->token, hash('sha256', $plainText))) {
            throw new InvalidArgumentException('Invalid refresh token.');
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            throw new InvalidArgumentException('Refresh token has expired.');
        }

        $user = $accessToken->tokenable;
        $accessToken->delete();

        $newRefreshToken = $this->issueRefreshToken($user);
        $newAccessToken = $this->issue($user);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => 900,
        ];
    }
}
