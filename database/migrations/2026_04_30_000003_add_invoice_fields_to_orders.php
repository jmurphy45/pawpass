<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('invoice_number')->nullable()->after('idempotency_key');
            $table->timestampTz('sent_at')->nullable()->after('invoice_number');
            $table->date('due_date')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'sent_at', 'due_date']);
        });
    }
};
