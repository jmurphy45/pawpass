<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->char('parking_spot_id', 26)->nullable()->after('kennel_unit_id');
            $table->timestampTz('arrived_at')->nullable()->after('actual_checkout_at');
            $table->timestampTz('arrival_acknowledged_at')->nullable()->after('arrived_at');

            $table->foreign('parking_spot_id')->references('id')->on('parking_spots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['parking_spot_id']);
            $table->dropColumn(['parking_spot_id', 'arrived_at', 'arrival_acknowledged_at']);
        });
    }
};
