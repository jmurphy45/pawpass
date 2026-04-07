<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders ADD COLUMN subtotal_cents integer NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE orders ADD COLUMN tax_amount_cents integer NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE orders ADD COLUMN stripe_tax_calc_id text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP COLUMN IF EXISTS subtotal_cents');
        DB::statement('ALTER TABLE orders DROP COLUMN IF EXISTS tax_amount_cents');
        DB::statement('ALTER TABLE orders DROP COLUMN IF EXISTS stripe_tax_calc_id');
    }
};
