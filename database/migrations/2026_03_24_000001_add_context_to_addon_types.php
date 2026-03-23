<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_types', function (Blueprint $table) {
            $table->text('context')->default('both')->after('is_active');
        });

        DB::statement("ALTER TABLE addon_types ADD CONSTRAINT addon_types_context_check CHECK (context IN ('boarding', 'daycare', 'both'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE addon_types DROP CONSTRAINT IF EXISTS addon_types_context_check');

        Schema::table('addon_types', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
