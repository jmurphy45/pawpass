<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'appointment_service_type') THEN
                    CREATE TYPE appointment_service_type AS ENUM ('daycare','boarding','grooming','vet');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'appointment_status') THEN
                    CREATE TYPE appointment_status AS ENUM (
                        'draft','pending','confirmed','checked_in','checked_out','no_show','cancelled'
                    );
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'bookable_resource_type') THEN
                    CREATE TYPE bookable_resource_type AS ENUM (
                        'kennel_unit','grooming_bay','exam_room','training_room'
                    );
                END IF;
            END\$\$
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS bookable_resource_type');
        DB::statement('DROP TYPE IF EXISTS appointment_status');
        DB::statement('DROP TYPE IF EXISTS appointment_service_type');
    }
};
