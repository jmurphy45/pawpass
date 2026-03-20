<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->boolean('auto_replenish_enabled')->default(false)->after('credits_alert_sent_at');
            $table->char('auto_replenish_package_id', 26)->nullable()->after('auto_replenish_enabled');
            $table->foreign('auto_replenish_package_id')->references('id')->on('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->dropForeign(['auto_replenish_package_id']);
            $table->dropColumn(['auto_replenish_enabled', 'auto_replenish_package_id']);
        });
    }
};
