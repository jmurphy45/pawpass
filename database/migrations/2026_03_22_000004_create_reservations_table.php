<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'reservation_status') THEN
                    CREATE TYPE reservation_status AS ENUM (
                        'pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'
                    );
                END IF;
            END\$\$
        ");

        Schema::create('reservations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('dog_id', 26);
            $table->char('customer_id', 26);
            $table->char('kennel_unit_id', 26)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->integer('nightly_rate_cents')->nullable();
            $table->text('notes')->nullable();
            $table->char('created_by', 26);
            $table->timestampTz('cancelled_at')->nullable();
            $table->char('cancelled_by', 26)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('dog_id')->references('id')->on('dogs');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('kennel_unit_id')->references('id')->on('kennel_units');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('cancelled_by')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE reservations ADD COLUMN status reservation_status NOT NULL DEFAULT 'pending'");

        DB::statement('CREATE INDEX reservations_tenant_status_idx ON reservations(tenant_id, status)');
        DB::statement('CREATE INDEX reservations_dog_id_idx ON reservations(dog_id)');
        DB::statement('CREATE INDEX reservations_unit_dates_idx ON reservations(kennel_unit_id, starts_at, ends_at)');
        DB::statement("CREATE INDEX reservations_unit_active_idx ON reservations(kennel_unit_id, starts_at, ends_at) WHERE status <> 'cancelled'");

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_dates_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        DB::statement('DROP TYPE IF EXISTS reservation_status');
    }
};
