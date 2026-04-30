<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->text('pims_patient_id')->nullable()->after('status');
            $table->text('pims_provider')->nullable()->after('pims_patient_id');
            $table->timestampTz('pims_synced_at')->nullable()->after('pims_provider');
            $table->text('microchip_number')->nullable()->after('pims_synced_at');
        });

        DB::statement('
            CREATE UNIQUE INDEX dogs_pims_unique
            ON dogs (tenant_id, pims_provider, pims_patient_id)
            WHERE pims_patient_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dogs_pims_unique');

        Schema::table('dogs', function (Blueprint $table) {
            $table->dropColumn(['pims_patient_id', 'pims_provider', 'pims_synced_at', 'microchip_number']);
        });
    }
};
