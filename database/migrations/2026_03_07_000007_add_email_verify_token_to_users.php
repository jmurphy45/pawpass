<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('email_verify_token')->nullable()->after('email_verified_at');
            $table->timestampTz('email_verify_expires_at')->nullable()->after('email_verify_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verify_token', 'email_verify_expires_at']);
        });
    }
};
