<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TYPE order_status ADD VALUE IF NOT EXISTS 'authorized' AFTER 'pending'");
        }
    }

    public function down(): void
    {
        // Postgres does not support removing enum values; no-op on down
    }
};
