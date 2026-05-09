<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->text('item_type')->nullable()->after('sort_order');
            $table->char('item_id', 26)->nullable()->after('item_type');
        });

        // Backfill existing line items as package type using their order's package_id
        DB::statement("
            UPDATE order_line_items oli
            SET item_type = 'package',
                item_id   = o.package_id
            FROM orders o
            WHERE oli.order_id = o.id
              AND o.package_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'item_id']);
        });
    }
};
