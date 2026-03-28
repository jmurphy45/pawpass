<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->unsignedInteger('tenant_limit')->nullable()->after('platform_fee_pct');
            $table->unsignedBigInteger('monthly_gmv_cap_cents')->nullable()->after('tenant_limit');
            $table->decimal('default_platform_fee_pct', 5, 2)->nullable()->after('monthly_gmv_cap_cents');
        });

        // Partial index for MTD GMV sum queries on founders plans
        DB::statement(
            "CREATE INDEX orders_tenant_paid_created_idx ON orders(tenant_id, created_at) WHERE status = 'paid'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS orders_tenant_paid_created_idx');

        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn(['tenant_limit', 'monthly_gmv_cap_cents', 'default_platform_fee_pct']);
        });
    }
};
