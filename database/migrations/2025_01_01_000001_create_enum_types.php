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
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'tenant_status') THEN
                    CREATE TYPE tenant_status AS ENUM ('active','pending_verification','suspended','cancelled');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
                    CREATE TYPE user_role AS ENUM ('platform_admin','business_owner','staff','customer');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'package_type') THEN
                    CREATE TYPE package_type AS ENUM ('one_time','subscription');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'order_status') THEN
                    CREATE TYPE order_status AS ENUM ('paid','partially_refunded','refunded','disputed');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'sub_status') THEN
                    CREATE TYPE sub_status AS ENUM ('active','past_due','cancelled','unpaid');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ledger_type') THEN
                    CREATE TYPE ledger_type AS ENUM (
                        'purchase','subscription','deduction','refund','goodwill',
                        'correction_add','correction_remove','expiry_removal',
                        'transfer_in','transfer_out'
                    );
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'notif_channel') THEN
                    CREATE TYPE notif_channel AS ENUM ('email','sms','in_app');
                END IF;
            END\$\$
        ");

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'notif_status') THEN
                    CREATE TYPE notif_status AS ENUM ('queued','sent','delivered','failed','skipped');
                END IF;
            END\$\$
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS notif_status');
        DB::statement('DROP TYPE IF EXISTS notif_channel');
        DB::statement('DROP TYPE IF EXISTS ledger_type');
        DB::statement('DROP TYPE IF EXISTS sub_status');
        DB::statement('DROP TYPE IF EXISTS order_status');
        DB::statement('DROP TYPE IF EXISTS package_type');
        DB::statement('DROP TYPE IF EXISTS user_role');
        DB::statement('DROP TYPE IF EXISTS tenant_status');
    }
};
