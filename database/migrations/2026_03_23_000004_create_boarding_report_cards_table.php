<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_report_cards', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('reservation_id', 26);
            $table->date('report_date');
            $table->text('notes');
            $table->char('created_by', 26);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('reservation_id')->references('id')->on('reservations');
            $table->foreign('created_by')->references('id')->on('users');
        });

        DB::statement('CREATE UNIQUE INDEX brc_reservation_date_uidx ON boarding_report_cards(reservation_id, report_date)');
        DB::statement('CREATE INDEX brc_tenant_date_idx ON boarding_report_cards(tenant_id, report_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_report_cards');
    }
};
