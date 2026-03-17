<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestampTz('trial_started_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->text('plan_billing_cycle')->nullable();
            $table->text('platform_stripe_customer_id')->nullable();
            $table->text('platform_stripe_sub_id')->nullable();
            $table->timestampTz('plan_current_period_end')->nullable();
            $table->boolean('plan_cancel_at_period_end')->default(false);
            $table->timestampTz('plan_past_due_since')->nullable();
        });

        DB::statement("ALTER TABLE tenants ADD COLUMN plan tenant_plan NOT NULL DEFAULT 'free'");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_plan_billing_cycle_check CHECK (plan_billing_cycle IN ('monthly', 'annual'))");
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'trial_started_at',
                'trial_ends_at',
                'plan_billing_cycle',
                'platform_stripe_customer_id',
                'platform_stripe_sub_id',
                'plan_current_period_end',
                'plan_cancel_at_period_end',
                'plan_past_due_since',
                'plan',
            ]);
        });
    }
};
