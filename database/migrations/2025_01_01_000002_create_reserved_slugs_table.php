<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_slugs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('slug')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_slugs');
    }
};
