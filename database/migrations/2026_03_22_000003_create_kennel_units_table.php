<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'kennel_unit_type') THEN
                    CREATE TYPE kennel_unit_type AS ENUM ('standard', 'suite', 'large', 'run');
                END IF;
            END\$\$
        ");

        Schema::create('kennel_units', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->text('name');
            $table->integer('capacity')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement("ALTER TABLE kennel_units ADD COLUMN type kennel_unit_type NOT NULL DEFAULT 'standard'");

        DB::statement('CREATE INDEX kennel_units_tenant_active_idx ON kennel_units(tenant_id, is_active)');
        DB::statement('CREATE INDEX kennel_units_tenant_sort_idx ON kennel_units(tenant_id, sort_order)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kennel_units');
        DB::statement('DROP TYPE IF EXISTS kennel_unit_type');
    }
};
