<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('stripe_payment_method_id')->nullable()->after('stripe_customer_id');
            $table->char('stripe_pm_last4', 4)->nullable()->after('stripe_payment_method_id');
            $table->text('stripe_pm_brand')->nullable()->after('stripe_pm_last4');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_method_id', 'stripe_pm_last4', 'stripe_pm_brand']);
        });
    }
};
