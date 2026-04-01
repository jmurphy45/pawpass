<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dog_vaccinations', function (Blueprint $table) {
            $table->timestampTz('warning_sent_at')->nullable()->after('notes');
            $table->timestampTz('urgent_sent_at')->nullable()->after('warning_sent_at');
        });

        DB::statement('CREATE INDEX dog_vaccinations_warning_pending_idx ON dog_vaccinations (expires_at) WHERE warning_sent_at IS NULL');
        DB::statement('CREATE INDEX dog_vaccinations_urgent_pending_idx ON dog_vaccinations (expires_at) WHERE urgent_sent_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dog_vaccinations_warning_pending_idx');
        DB::statement('DROP INDEX IF EXISTS dog_vaccinations_urgent_pending_idx');

        Schema::table('dog_vaccinations', function (Blueprint $table) {
            $table->dropColumn(['warning_sent_at', 'urgent_sent_at']);
        });
    }
};
