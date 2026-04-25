<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TYPE notif_channel ADD VALUE IF NOT EXISTS 'webpush' AFTER 'in_app'");
        }
    }

    public function down(): void
    {
        // Postgres does not support removing enum values; no-op on down
    }
};
