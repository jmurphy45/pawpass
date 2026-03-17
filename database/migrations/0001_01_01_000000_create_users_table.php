<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('customer_id', 26)->nullable();
            $table->text('name');
            $table->text('email');
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('password')->nullable();
            $table->text('role')->default('customer');
            $table->text('status')->default('active');
            $table->text('phone')->nullable();
            $table->text('timezone')->nullable();
            $table->text('invite_token')->nullable();
            $table->timestampTz('invite_expires_at')->nullable();
            $table->text('remember_token')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('platform_admin','business_owner','staff','customer'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','pending_verification','pending_invite','suspended'))");

        DB::statement('CREATE UNIQUE INDEX users_tenant_email_key ON users(tenant_id, email) WHERE deleted_at IS NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->text('email')->primary();
            $table->text('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->char('user_id', 26)->nullable()->index();
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
