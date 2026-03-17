<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('customer_id', 26);
            $table->char('package_id', 26);
            $table->char('dog_id', 26);
            $table->text('stripe_sub_id')->nullable();
            $table->text('stripe_customer_id')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('package_id')->references('id')->on('packages');
            $table->foreign('dog_id')->references('id')->on('dogs');
        });

        DB::statement("ALTER TABLE subscriptions ADD COLUMN status sub_status NOT NULL DEFAULT 'active'");
        DB::statement('CREATE UNIQUE INDEX subs_stripe_sub_id_key ON subscriptions(stripe_sub_id) WHERE stripe_sub_id IS NOT NULL');
        DB::statement('CREATE INDEX subs_tenant_id_idx ON subscriptions(tenant_id)');
        DB::statement('CREATE INDEX subs_customer_id_idx ON subscriptions(customer_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
