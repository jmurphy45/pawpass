<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_log', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('actor_id', 26)->nullable();
            $table->text('actor_role')->nullable();
            $table->text('action');
            $table->text('target_type')->nullable();
            $table->text('target_id')->nullable();
            $table->json('context')->nullable();
            $table->text('ip_address')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('actor_id')->references('id')->on('users');
        });

        DB::statement('CREATE INDEX audit_log_actor_id_idx ON platform_audit_log(actor_id)');
        DB::statement('CREATE INDEX audit_log_created_at_idx ON platform_audit_log(created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_log');
    }
};
