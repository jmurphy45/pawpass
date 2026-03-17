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
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'tenant_plan') THEN
                    CREATE TYPE tenant_plan AS ENUM ('free', 'starter', 'pro', 'business');
                END IF;
            END\$\$
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS tenant_plan');
    }
};
