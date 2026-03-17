<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->text('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('credit_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('stripe_price_id')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement("ALTER TABLE packages ADD COLUMN type package_type NOT NULL DEFAULT 'one_time'");
        DB::statement('CREATE INDEX packages_tenant_id_idx ON packages(tenant_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
