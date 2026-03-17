<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('dog_id', 26);
            $table->char('checked_in_by', 26);
            $table->char('checked_out_by', 26)->nullable();
            $table->timestampTz('checked_in_at');
            $table->timestampTz('checked_out_at')->nullable();
            $table->boolean('zero_credit_override')->default(false);
            $table->text('override_note')->nullable();
            $table->char('edited_by', 26)->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->text('edit_note')->nullable();
            $table->timestampTz('original_in')->nullable();
            $table->timestampTz('original_out')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('dog_id')->references('id')->on('dogs');
            $table->foreign('checked_in_by')->references('id')->on('users');
            $table->foreign('checked_out_by')->references('id')->on('users');
            $table->foreign('edited_by')->references('id')->on('users');
        });

        DB::statement('CREATE INDEX attend_tenant_active_idx ON attendances(tenant_id, checked_out_at) WHERE checked_out_at IS NULL');
        DB::statement('CREATE INDEX attend_dog_id_idx ON attendances(dog_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
