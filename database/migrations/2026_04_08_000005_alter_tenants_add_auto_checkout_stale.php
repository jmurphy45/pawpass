<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants ADD COLUMN auto_checkout_stale boolean NOT NULL DEFAULT false");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tenants DROP COLUMN IF EXISTS auto_checkout_stale");
    }
};
