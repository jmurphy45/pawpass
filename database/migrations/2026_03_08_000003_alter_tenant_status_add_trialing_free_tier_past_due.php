<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE tenant_status ADD VALUE IF NOT EXISTS 'trialing'");
        DB::statement("ALTER TYPE tenant_status ADD VALUE IF NOT EXISTS 'free_tier'");
        DB::statement("ALTER TYPE tenant_status ADD VALUE IF NOT EXISTS 'past_due'");
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values without recreating the type.
        // A full rollback would require migrating existing data.
    }
};
