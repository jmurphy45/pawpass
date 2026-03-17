<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE RULE credit_ledger_no_update AS ON UPDATE TO credit_ledger
            DO INSTEAD NOTHING
        ');

        DB::statement('
            CREATE RULE credit_ledger_no_delete AS ON DELETE TO credit_ledger
            DO INSTEAD NOTHING
        ');
    }

    public function down(): void
    {
        DB::statement('DROP RULE IF EXISTS credit_ledger_no_update ON credit_ledger');
        DB::statement('DROP RULE IF EXISTS credit_ledger_no_delete ON credit_ledger');
    }
};
