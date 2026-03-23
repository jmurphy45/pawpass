<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_requirements', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->text('vaccine_name');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement('CREATE UNIQUE INDEX vaccination_requirements_tenant_vaccine_uidx ON vaccination_requirements(tenant_id, vaccine_name)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_requirements');
    }
};
