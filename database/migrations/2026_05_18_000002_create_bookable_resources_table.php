<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_resources', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->text('name');
            $table->integer('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->char('kennel_unit_id', 26)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('kennel_unit_id')->references('id')->on('kennel_units')->onDelete('set null');
        });

        DB::statement("ALTER TABLE bookable_resources ADD COLUMN resource_type bookable_resource_type NOT NULL DEFAULT 'exam_room'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_resources');
    }
};
