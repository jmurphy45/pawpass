<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_customer_id_fk FOREIGN KEY (customer_id) REFERENCES customers(id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_customer_id_fk');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tenant_id_fk');
    }
};
