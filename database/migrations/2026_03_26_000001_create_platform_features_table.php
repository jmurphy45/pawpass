<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_features', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_marketing')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_features');
    }
};
