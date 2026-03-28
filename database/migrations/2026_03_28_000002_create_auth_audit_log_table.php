<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 26)->nullable();
            $table->string('event_type', 50);
            $table->string('fp_hash', 256)->nullable();
            $table->string('fp_match', 10)->nullable();
            $table->float('fp_similarity_score')->nullable();
            $table->smallInteger('risk_score')->nullable();
            $table->json('risk_factors')->nullable();
            $table->string('action_taken', 100)->nullable();
            $table->string('ip_address', 45);
            $table->string('reason', 255)->nullable();
            $table->timestampTz('timestamp');

            $table->index('user_id');
            $table->index('event_type');
        });

        DB::statement("ALTER TABLE auth_audit_log ADD CONSTRAINT aal_event_type_check CHECK (event_type IN ('TOKEN_CREATED','LOGIN_SUCCESS','LOGIN_FAILED','CLEANUP_RUN'))");
        DB::statement("ALTER TABLE auth_audit_log ADD CONSTRAINT aal_fp_match_check CHECK (fp_match IN ('true','partial','false'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_log');
    }
};
