<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->text('name');
            $table->text('slug')->unique();
            // owner_user_id FK added after users table in alter_tenants_add_owner_fk
            $table->char('owner_user_id', 26)->nullable();
            $table->text('stripe_account_id')->nullable();
            $table->timestampTz('stripe_onboarded_at')->nullable();
            $table->decimal('platform_fee_pct', 5, 2)->default(5.0);
            $table->text('payout_schedule')->default('monthly');
            $table->integer('low_credit_threshold')->default(2);
            $table->boolean('checkin_block_at_zero')->default(true);
            $table->text('timezone')->default('America/Chicago');
            $table->char('primary_color', 7)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();
        });

        // Use the PG enum for status
        DB::statement("ALTER TABLE tenants ADD COLUMN status tenant_status NOT NULL DEFAULT 'pending_verification'");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_slug_format CHECK (slug ~ '^[a-z0-9-]{3,63}$')");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_primary_color_format CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$' OR primary_color IS NULL)");

        // Partial unique index for active slugs
        DB::statement('CREATE UNIQUE INDEX tenants_slug_active_key ON tenants(slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
