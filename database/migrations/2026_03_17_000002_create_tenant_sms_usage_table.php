<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sms_usage', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26);
            $table->char('period', 7);
            $table->integer('segments_used')->default(0);
            $table->timestampTz('billed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sms_usage');
    }
};
