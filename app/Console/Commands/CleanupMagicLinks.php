<?php

namespace App\Console\Commands;

use App\Models\AuthAuditLog;
use App\Models\MagicLinkToken;
use Illuminate\Console\Command;

class CleanupMagicLinks extends Command
{
    protected $signature = 'auth:cleanup-magic-links';

    protected $description = 'Soft-delete expired/used magic-link tokens and hard-delete old soft-deleted records.';

    /**
     * Run the cleanup in two passes:
     * 1. Soft-delete tokens that are expired or already used.
     * 2. Hard-delete records that were soft-deleted more than 90 days ago.
     */
    public function handle(): int
    {
        $softDeleted = 0;
        $hardDeleted = 0;

        // Pass 1: soft-delete expired or used tokens (still within the soft-delete window)
        MagicLinkToken::withoutTrashed()
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                    ->orWhereNotNull('used_at');
            })
            ->chunkById(500, function ($tokens) use (&$softDeleted) {
                foreach ($tokens as $token) {
                    $token->delete();
                    $softDeleted++;
                }
            });

        // Pass 2: hard-delete records soft-deleted more than 90 days ago
        MagicLinkToken::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(90))
            ->chunkById(500, function ($tokens) use (&$hardDeleted) {
                foreach ($tokens as $token) {
                    $token->forceDelete();
                    $hardDeleted++;
                }
            });

        AuthAuditLog::create([
            'event_type' => 'CLEANUP_RUN',
            'ip_address' => '0.0.0.0',
            'action_taken' => "soft_deleted={$softDeleted},hard_deleted={$hardDeleted}",
        ]);

        $this->info("Cleanup complete. Soft-deleted: {$softDeleted}, hard-deleted: {$hardDeleted}.");

        return Command::SUCCESS;
    }
}
