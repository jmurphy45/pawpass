<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->unsignedBigInteger('breed_id')->nullable()->after('name');
            $table->foreign('breed_id')->references('id')->on('breeds')->nullOnDelete();
        });

        // Best-effort data migration: match existing breed text to breeds table
        DB::statement('
            UPDATE dogs SET breed_id = (
                SELECT id FROM breeds WHERE LOWER(breeds.name) = LOWER(dogs.breed)
            )
            WHERE dogs.breed IS NOT NULL
        ');

        Schema::table('dogs', function (Blueprint $table) {
            $table->dropColumn('breed');
        });
    }

    public function down(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $table->text('breed')->nullable()->after('name');
        });

        DB::statement('
            UPDATE dogs SET breed = (
                SELECT name FROM breeds WHERE breeds.id = dogs.breed_id
            )
            WHERE dogs.breed_id IS NOT NULL
        ');

        Schema::table('dogs', function (Blueprint $table) {
            $table->dropForeign(['breed_id']);
            $table->dropColumn('breed_id');
        });
    }
};
