<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants ALTER COLUMN is_publicly_listed SET DEFAULT true");
        DB::statement("UPDATE tenants SET is_publicly_listed = true WHERE is_publicly_listed = false");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tenants ALTER COLUMN is_publicly_listed SET DEFAULT false");
    }
};
