<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscription_events', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->text('event_type');
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscription_events');
    }
};
