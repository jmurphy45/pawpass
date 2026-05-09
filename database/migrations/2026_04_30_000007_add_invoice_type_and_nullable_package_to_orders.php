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
            $table->char('package_id', 26)->nullable()->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type IN ('daycare', 'boarding', 'invoice'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type IN ('daycare', 'boarding'))");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->char('package_id', 26)->nullable(false)->change();
        });
    }
};
