<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dogs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('customer_id', 26);
            $table->text('name');
            $table->text('breed')->nullable();
            $table->date('dob')->nullable();
            $table->text('sex')->nullable();
            $table->text('photo_url')->nullable();
            $table->text('vet_name')->nullable();
            $table->text('vet_phone')->nullable();
            $table->integer('credit_balance')->default(0);
            $table->timestampTz('credits_expire_at')->nullable();
            $table->timestampTz('credits_alert_sent_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('customer_id')->references('id')->on('customers');
        });

        DB::statement("ALTER TABLE dogs ADD CONSTRAINT dogs_sex_check CHECK (sex IN ('male','female','unknown') OR sex IS NULL)");

        DB::statement('CREATE INDEX dogs_tenant_id_idx ON dogs(tenant_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX dogs_customer_id_idx ON dogs(customer_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX dogs_credits_expire_at_idx ON dogs(credits_expire_at) WHERE credits_expire_at IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('dogs');
    }
};
