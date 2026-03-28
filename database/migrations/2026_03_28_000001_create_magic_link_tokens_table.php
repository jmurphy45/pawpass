<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_link_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('user_id', 26);
            $table->string('token_hash', 256);
            $table->string('fp_hash', 256);
            $table->json('fp_components')->nullable();
            $table->string('ip_address', 45);
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('token_hash');
            $table->index(['user_id', 'deleted_at', 'used_at', 'expires_at'], 'mlt_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_link_tokens');
    }
};
