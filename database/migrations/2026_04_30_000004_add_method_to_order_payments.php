<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->text('method')->nullable()->after('stripe_payment_method');
        });

        DB::statement("UPDATE order_payments SET method = 'stripe' WHERE method IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_payments ALTER COLUMN method SET NOT NULL');
            DB::statement("ALTER TABLE order_payments ALTER COLUMN method SET DEFAULT 'stripe'");
            DB::statement("ALTER TABLE order_payments ADD CONSTRAINT order_payments_method_check CHECK (method IN ('stripe','cash','check','other'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_payments DROP CONSTRAINT IF EXISTS order_payments_method_check');
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
