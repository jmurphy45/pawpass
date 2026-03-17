<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_owner_user_id_fk FOREIGN KEY (owner_user_id) REFERENCES users(id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_owner_user_id_fk');
    }
};
