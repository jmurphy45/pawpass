<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26)->nullable();
            $table->text('name');
            $table->text('email');
            $table->text('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('CREATE UNIQUE INDEX customers_tenant_email_key ON customers(tenant_id, email) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX customers_tenant_id_idx ON customers(tenant_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
