<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('pims_client_id')->nullable()->after('notes');
            $table->text('pims_provider')->nullable()->after('pims_client_id');
            $table->timestampTz('pims_synced_at')->nullable()->after('pims_provider');
        });

        DB::statement('
            CREATE UNIQUE INDEX customers_pims_unique
            ON customers (tenant_id, pims_provider, pims_client_id)
            WHERE pims_client_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_pims_unique');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['pims_client_id', 'pims_provider', 'pims_synced_at']);
        });
    }
};
