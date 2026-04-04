<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants ADD COLUMN business_address text");
        DB::statement("ALTER TABLE tenants ADD COLUMN business_city text");
        DB::statement("ALTER TABLE tenants ADD COLUMN business_state char(2)");
        DB::statement("ALTER TABLE tenants ADD COLUMN business_zip char(10)");
        DB::statement("ALTER TABLE tenants ADD COLUMN business_phone text");
        DB::statement("ALTER TABLE tenants ADD COLUMN business_description text CHECK (char_length(business_description) <= 280)");
        DB::statement("ALTER TABLE tenants ADD COLUMN is_publicly_listed boolean NOT NULL DEFAULT false");
        DB::statement("CREATE INDEX tenants_state_city_idx ON tenants (business_state, business_city)");
        DB::statement("CREATE INDEX tenants_zip_idx ON tenants (business_zip)");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tenants_state_city_idx');
        DB::statement('DROP INDEX IF EXISTS tenants_zip_idx');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_address');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_city');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_state');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_zip');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_phone');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_description');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS is_publicly_listed');
    }
};
