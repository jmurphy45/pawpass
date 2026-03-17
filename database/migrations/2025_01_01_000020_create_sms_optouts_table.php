<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_optouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('phone');
            $table->timestampTz('created_at');
        });

        DB::statement('CREATE UNIQUE INDEX sms_optouts_phone_key ON sms_optouts(phone)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_optouts');
    }
};
