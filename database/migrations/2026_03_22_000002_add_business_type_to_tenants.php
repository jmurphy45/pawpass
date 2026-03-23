<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'business_type') THEN
                    CREATE TYPE business_type AS ENUM ('daycare', 'kennel', 'hybrid');
                END IF;
            END\$\$
        ");

        DB::statement("ALTER TABLE tenants ADD COLUMN business_type business_type NOT NULL DEFAULT 'daycare'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS business_type');
        DB::statement('DROP TYPE IF EXISTS business_type');
    }
};
