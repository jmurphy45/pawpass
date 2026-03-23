<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kennel_units', function (Blueprint $table) {
            $table->integer('nightly_rate_cents')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('kennel_units', function (Blueprint $table) {
            $table->dropColumn('nightly_rate_cents');
        });
    }
};
