<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_events', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->text('event_type');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        DB::statement('CREATE INDEX tenant_events_tenant_type_idx ON tenant_events(tenant_id, event_type)');
        DB::statement('CREATE INDEX tenant_events_tenant_created_idx ON tenant_events(tenant_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_events');
    }
};
