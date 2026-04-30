<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dog_vaccinations', function (Blueprint $table) {
            $table->text('pims_record_id')->nullable()->after('notes');
            $table->text('pims_provider')->nullable()->after('pims_record_id');
            $table->text('source')->default('manual')->after('pims_provider');
        });

        DB::statement("ALTER TABLE dog_vaccinations ADD CONSTRAINT dog_vaccinations_source_check CHECK (source IN ('manual', 'pims'))");

        DB::statement('
            CREATE UNIQUE INDEX dog_vaccinations_pims_unique
            ON dog_vaccinations (dog_id, pims_provider, pims_record_id)
            WHERE pims_record_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dog_vaccinations_pims_unique');
        DB::statement('ALTER TABLE dog_vaccinations DROP CONSTRAINT IF EXISTS dog_vaccinations_source_check');

        Schema::table('dog_vaccinations', function (Blueprint $table) {
            $table->dropColumn(['pims_record_id', 'pims_provider', 'source']);
        });
    }
};
