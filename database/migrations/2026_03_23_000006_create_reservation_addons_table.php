<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_addons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('reservation_id', 26);
            $table->char('addon_type_id', 26);
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->text('note')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('reservation_id')->references('id')->on('reservations');
            $table->foreign('addon_type_id')->references('id')->on('addon_types');
        });

        DB::statement('CREATE INDEX reservation_addons_reservation_idx ON reservation_addons(reservation_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_addons');
    }
};
