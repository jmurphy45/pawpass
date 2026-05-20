<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daycare_capacity_windows', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->text('label');
            $table->smallInteger('day_of_week')->nullable();
            $table->date('specific_date')->nullable();
            $table->time('opens_at');
            $table->time('closes_at');
            $table->integer('max_dogs');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement("ALTER TABLE daycare_capacity_windows ADD COLUMN recurrence text NOT NULL DEFAULT 'weekly' CHECK (recurrence IN ('weekly','one_time'))");
        DB::statement("ALTER TABLE daycare_capacity_windows ADD CONSTRAINT dcw_recurrence_fields_check CHECK ((recurrence = 'weekly' AND day_of_week IS NOT NULL) OR (recurrence = 'one_time' AND specific_date IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('daycare_capacity_windows');
    }
};
