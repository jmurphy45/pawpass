<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dog_vaccinations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('dog_id', 26);
            $table->text('vaccine_name');
            $table->date('administered_at');
            $table->date('expires_at')->nullable();
            $table->text('administered_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('dog_id')->references('id')->on('dogs');
        });

        DB::statement('CREATE INDEX dog_vaccinations_dog_id_idx ON dog_vaccinations(dog_id)');
        DB::statement('CREATE INDEX dog_vaccinations_tenant_dog_idx ON dog_vaccinations(tenant_id, dog_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dog_vaccinations');
    }
};
