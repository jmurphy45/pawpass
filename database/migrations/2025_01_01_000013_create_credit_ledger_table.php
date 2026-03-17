<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('dog_id', 26);
            $table->integer('delta');
            $table->integer('balance_after');
            $table->timestampTz('expires_at')->nullable();
            $table->char('order_id', 26)->nullable();
            $table->char('attendance_id', 26)->nullable();
            $table->char('subscription_id', 26)->nullable();
            $table->char('parent_ledger_id', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->text('note')->nullable();
            // No updated_at — append-only table
            $table->timestampTz('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('dog_id')->references('id')->on('dogs');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('attendance_id')->references('id')->on('attendances');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Self-referential FK added after table creation so the primary key constraint exists
        DB::statement('ALTER TABLE credit_ledger ADD CONSTRAINT credit_ledger_parent_ledger_id_fk FOREIGN KEY (parent_ledger_id) REFERENCES credit_ledger(id)');

        DB::statement('ALTER TABLE credit_ledger ADD COLUMN type ledger_type NOT NULL');
        DB::statement('CREATE INDEX ledger_dog_created_idx ON credit_ledger(dog_id, created_at DESC)');
        DB::statement('CREATE INDEX ledger_tenant_id_idx ON credit_ledger(tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};
