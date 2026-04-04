<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE tenants
            ADD COLUMN auto_charge_at_zero_package_id char(26) NULL
            REFERENCES packages(id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS auto_charge_at_zero_package_id');
    }
};
