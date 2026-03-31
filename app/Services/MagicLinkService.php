<?php

namespace App\Services;

use App\Auth\MagicLink\Action;
use App\Auth\MagicLink\VerificationResult;
use App\Models\AuthAuditLog;
use App\Models\MagicLinkToken;
use App\Models\User;

class MagicLinkService
{
    /**
     * Generate a magic-link token for the given user, store a hash of it, and return the raw token.
     *
     * @param  array<string, mixed>  $fpComponents  Fingerprint components from the frontend.
     */
    public function generateToken(User $user, array $fpComponents, string $ip, int $expiryMinutes = 15): string
    {
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $rawToken);
        $fpHash = hash('sha256', json_encode($fpComponents));

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'fp_hash' => $fpHash,
            'fp_components' => $fpComponents,
            'ip_address' => $ip,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        AuthAuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'TOKEN_CREATED',
            'fp_hash' => $fpHash,
            'ip_address' => $ip,
        ]);

        return $rawToken;
    }

    /**
     * Verify a raw magic-link token against stored hash, apply fingerprint risk scoring,
     * and return a VerificationResult describing the action to take.
     *
     * @param  array<string, mixed>  $fpComponents
     */
    public function verifyToken(string $rawToken, array $fpComponents, string $ip): VerificationResult
    {
        $tokenHash = hash('sha256', $rawToken);

        /** @var MagicLinkToken|null $token */
        $token = MagicLinkToken::withoutTrashed()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            AuthAuditLog::create([
                'event_type' => 'LOGIN_FAILED',
                'ip_address' => $ip,
                'reason' => 'INVALID_OR_EXPIRED_TOKEN',
            ]);

            return VerificationResult::failure();
        }

        $user = $token->user;
        $storedComponents = $token->fp_components ?? [];
        $fpSimilarityScore = $this->computeFpSimilarity($storedComponents, $fpComponents);
        $incomingFpHash = hash('sha256', json_encode($fpComponents));

        $riskScore = 0;
        $riskFactors = [];

        // Fingerprint risk
        if ($fpSimilarityScore < 0.6) {
            $riskScore += 2;
            $riskFactors[] = 'fp_low_similarity';
        } elseif ($fpSimilarityScore < 1.0) {
            $riskScore += 1;
            $riskFactors[] = 'fp_partial_similarity';
        }

        // IP subnet risk (IPv4 /24)
        if ($this->isSubnetMismatch($token->ip_address, $ip)) {
            $riskScore += 1;
            $riskFactors[] = 'ip_subnet_mismatch';
        }

        // Token age risk
        if ($token->created_at->diffInMinutes(now()) > 10) {
            $riskScore += 1;
            $riskFactors[] = 'token_age_exceeded';
        }

        $action = match (true) {
            $riskScore === 0 => Action::AUTHENTICATE,
            $riskScore === 1 => Action::AUTHENTICATE_FLAGGED,
            $riskScore === 2 => Action::STEP_UP,
            default => Action::BLOCK,
        };

        // Only consume the token on successful authentication
        if ($action === Action::AUTHENTICATE || $action === Action::AUTHENTICATE_FLAGGED) {
            $token->update(['used_at' => now()]);
        }

        $fpMatch = match (true) {
            $fpSimilarityScore >= 1.0 => 'true',
            $fpSimilarityScore >= 0.6 => 'partial',
            default => 'false',
        };

        AuthAuditLog::create([
            'user_id' => $user->id,
            'event_type' => in_array($action, [Action::AUTHENTICATE, Action::AUTHENTICATE_FLAGGED])
                                          ? 'LOGIN_SUCCESS'
                                          : 'LOGIN_FAILED',
            'fp_hash' => $incomingFpHash,
            'fp_match' => $fpMatch,
            'fp_similarity_score' => $fpSimilarityScore,
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'action_taken' => $action->value,
            'ip_address' => $ip,
            'reason' => $riskScore === 0 ? null : implode(',', $riskFactors),
        ]);

        return new VerificationResult(
            action: $action,
            user: $user,
            riskScore: $riskScore,
            riskFactors: $riskFactors,
            fpSimilarityScore: $fpSimilarityScore,
        );
    }

    /**
     * Soft-delete all active (unused, non-expired) tokens for the user.
     */
    public function revokeAllTokens(User $user): void
    {
        MagicLinkToken::active()
            ->where('user_id', $user->id)
            ->get()
            ->each(fn (MagicLinkToken $t) => $t->delete());

        AuthAuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'LOGIN_FAILED',
            'ip_address' => request()->ip() ?? '0.0.0.0',
            'reason' => 'USER_REVOKED_TOKENS',
        ]);
    }

    /**
     * Compare two sets of fingerprint components (ua, screen, lang, tz, platform).
     * Each matching key contributes 0.2, max score 1.0.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $incoming
     */
    private function computeFpSimilarity(array $stored, array $incoming): float
    {
        $keys = ['ua', 'screen', 'lang', 'tz', 'platform'];
        $matches = 0;

        foreach ($keys as $key) {
            if (isset($stored[$key], $incoming[$key]) && $stored[$key] === $incoming[$key]) {
                $matches++;
            }
        }

        return round($matches * 0.2, 2);
    }

    /**
     * Returns true if the two IPv4 addresses differ in the first three octets (/24 subnet).
     * IPv6 addresses are compared directly — no subnet match attempted.
     */
    private function isSubnetMismatch(string $storedIp, string $incomingIp): bool
    {
        // If either address is IPv6, fall back to full string comparison
        if (str_contains($storedIp, ':') || str_contains($incomingIp, ':')) {
            return $storedIp !== $incomingIp;
        }

        $storedOctets = explode('.', $storedIp);
        $incomingOctets = explode('.', $incomingIp);

        if (count($storedOctets) !== 4 || count($incomingOctets) !== 4) {
            return $storedIp !== $incomingIp;
        }

        return array_slice($storedOctets, 0, 3) !== array_slice($incomingOctets, 0, 3);
    }
}
