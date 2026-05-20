<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type = ANY (ARRAY['daycare'::text, 'boarding'::text, 'invoice'::text, 'vet'::text, 'grooming'::text, 'daycare_booking'::text]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type = ANY (ARRAY['daycare'::text, 'boarding'::text, 'invoice'::text]))");
    }
};
