<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plan_features', function (Blueprint $table) {
            $table->char('plan_id', 26);
            $table->char('feature_id', 26);
            $table->primary(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('platform_plans')->onDelete('cascade');
            $table->foreign('feature_id')->references('id')->on('platform_features')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plan_features');
    }
};
