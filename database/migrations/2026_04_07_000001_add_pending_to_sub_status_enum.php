<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE sub_status ADD VALUE IF NOT EXISTS 'pending' BEFORE 'active'");
        DB::statement("ALTER TABLE subscriptions ALTER COLUMN status SET DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions ALTER COLUMN status SET DEFAULT 'active'");
    }
};
