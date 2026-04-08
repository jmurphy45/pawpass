<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TABLE dogs ADD COLUMN status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dogs DROP COLUMN status');
    }
};
