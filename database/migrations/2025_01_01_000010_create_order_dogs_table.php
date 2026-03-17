<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_dogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('order_id', 26);
            $table->char('dog_id', 26);
            $table->integer('credits_issued');

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('dog_id')->references('id')->on('dogs');
            $table->unique(['order_id', 'dog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_dogs');
    }
};
