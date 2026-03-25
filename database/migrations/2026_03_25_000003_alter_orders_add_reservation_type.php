<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('reservation_id', 26)->nullable()->after('id');
            $table->text('type')->default('daycare')->after('reservation_id');
            $table->char('package_id', 26)->nullable()->change();

            $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type IN ('daycare', 'boarding'))");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropColumn(['reservation_id', 'type']);
            $table->char('package_id', 26)->nullable(false)->change();
        });

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
    }
};
